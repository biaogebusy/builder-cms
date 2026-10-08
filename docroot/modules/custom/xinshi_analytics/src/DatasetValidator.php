<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

/** Resolves explicit mappings against current field definitions, never SQL paths. */
final class DatasetValidator {

  /** Constructs the validator without requiring Node or another source module. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly EntityFieldManagerInterface $fields,
    private readonly LanguageManagerInterface $languages,
  ) {}

  /** Validates exportable configuration and returns field definitions per bundle. */
  public function validate(AnalyticsDataset $dataset): array {
    $entityType = $dataset->get('entity_type');
    $bundles = $dataset->get('bundles');
    $policy = $dataset->get('query_policy');
    $dimensions = $dataset->get('dimensions');
    $filters = $dataset->get('filters');
    if (!CountQuery::alias($dataset->id()) || !CountQuery::text($dataset->label())
      || !CountQuery::integer($dataset->get('version')) || !is_bool($dataset->get('status'))
      || !CountQuery::alias($entityType) || !CountQuery::strings($bundles, 100, 1)
      || count(array_filter($bundles, static fn(string $bundle): bool =>
        preg_match('/^[a-z0-9_]{1,128}$/D', $bundle) === 1)) !== count($bundles)
      || !CountQuery::alias($dataset->get('time_field'))
      || !is_array($dimensions) || count($dimensions) > 20
      || !is_array($filters) || count($filters) > 20
      || !CountQuery::keys($policy, ['timezone', 'languages', 'max_calendar_months',
        'max_scanned_entities', 'max_groups', 'max_seconds'])
      || !CountQuery::timezone($policy['timezone']) || !CountQuery::strings($policy['languages'], 100, 1)
      || count(array_filter($policy['languages'], CountQuery::language(...))) !== count($policy['languages'])
      || array_diff($policy['languages'], array_keys($this->languages->getLanguages()))
      || !CountQuery::integer($policy['max_calendar_months'], 120)
      || !CountQuery::integer($policy['max_scanned_entities'])
      || !CountQuery::integer($policy['max_groups'], 100)
      || !CountQuery::integer($policy['max_seconds'], 60)) {
      throw new AnalyticsException('dataset_unavailable');
    }
    $type = $this->entityTypes->getDefinition($entityType, FALSE);
    if (!$type instanceof ContentEntityTypeInterface
      || !is_subclass_of($type->getClass(), EntityPublishedInterface::class)
      || !$type->getKey('published') || !$type->getKey('id')
      || !$this->entityTypes->getStorage($entityType) instanceof SqlEntityStorageInterface) {
      throw new AnalyticsException('dataset_unavailable');
    }
    foreach ($dimensions as $alias => $dimension) {
      if (!CountQuery::alias($alias) || !CountQuery::keys($dimension, ['label', 'field', 'kind'])
        || !CountQuery::text($dimension['label']) || !CountQuery::alias($dimension['field'])
        || !in_array($dimension['kind'], ['month', 'category'], TRUE)
        || (($dimension['kind'] === 'month') !== ($dimension['field'] === $dataset->get('time_field')))) {
        throw new AnalyticsException('dataset_unavailable');
      }
    }
    foreach ($filters as $alias => $filter) {
      if (!CountQuery::alias($alias) || !CountQuery::keys($filter, ['label', 'field', 'operators', 'values'])
        || !CountQuery::text($filter['label']) || !CountQuery::alias($filter['field'])
        || !CountQuery::strings($filter['operators'], 2, 1) || array_diff($filter['operators'], ['eq', 'in'])
        || !CountQuery::strings($filter['values'], 100, 1)
        || count(array_filter($filter['values'], CountQuery::text(...))) !== count($filter['values'])) {
        throw new AnalyticsException('dataset_unavailable');
      }
    }
    $resolved = [];
    foreach ($bundles as $bundle) {
      $bundleType = $type->getBundleEntityType();
      if ($bundleType ? !$this->entityTypes->getStorage($bundleType)->load($bundle) : $bundle !== $entityType) {
        throw new AnalyticsException('dataset_unavailable');
      }
      $definitions = $this->fields->getFieldDefinitions($entityType, $bundle);
      $published = $definitions[$type->getKey('published')] ?? NULL;
      if (($definitions[$type->getKey('id')] ?? NULL)?->getType() !== 'integer'
        || !$published || $published->isComputed() || $published->getFieldStorageDefinition()->getCardinality() !== 1) {
        throw new AnalyticsException('dataset_unavailable');
      }
      $required = [$dataset->get('time_field') => 'month'];
      foreach ($dimensions as $dimension) {
        $required[$dimension['field']] = $dimension['kind'];
      }
      foreach ($filters as $filter) {
        $required[$filter['field']] = 'category';
      }
      // The time field cannot be repurposed as a category or enum filter.
      if ($required[$dataset->get('time_field')] !== 'month') {
        throw new AnalyticsException('dataset_unavailable');
      }
      foreach ($required as $name => $kind) {
        $field = $definitions[$name] ?? NULL;
        $storage = $field?->getFieldStorageDefinition();
        $fieldType = $field?->getType();
        $supported = $kind === 'month' ? in_array($fieldType, ['timestamp', 'created', 'changed'], TRUE)
          : in_array($fieldType, ['string', 'list_string', 'list_integer', 'boolean'], TRUE)
            || ($fieldType === 'entity_reference' && $name === $type->getKey('bundle')
              && $field->getSetting('target_type') === $bundleType);
        if (!$field || !$supported || $field->isComputed() || $storage->getCardinality() !== 1) {
          throw new AnalyticsException('dataset_unavailable');
        }
      }
      $resolved[$bundle] = $definitions;
    }
    return $resolved;
  }

  /** Tracks source modules, bundles and fields so removal cannot leave active mappings. */
  public function dependencies(AnalyticsDataset $dataset): array {
    $definitions = $this->validate($dataset);
    $type = $this->entityTypes->getDefinition($dataset->get('entity_type'));
    $dependencies = ['module' => [$type->getProvider()], 'config' => []];
    foreach ($definitions as $bundle => $fields) {
      if ($bundleType = $type->getBundleEntityType()) {
        $dependencies['config'][] = $this->entityTypes->getStorage($bundleType)->load($bundle)->getConfigDependencyName();
      }
      $names = [$dataset->get('time_field'), $type->getKey('published')];
      foreach (['dimensions', 'filters'] as $group) {
        foreach ($dataset->get($group) as $mapping) {
          $names[] = $mapping['field'];
        }
      }
      foreach (array_unique($names) as $name) {
        $field = $fields[$name];
        $storage = $field->getFieldStorageDefinition();
        $dependencies['module'][] = $storage->getProvider();
        foreach ([$field, $storage] as $dependency) {
          if ($dependency instanceof ConfigEntityInterface) {
            $dependencies['config'][] = $dependency->getConfigDependencyName();
          }
        }
      }
    }
    foreach ($dataset->get('query_policy')['languages'] as $language) {
      // English is available without the optional language module.
      if ($this->entityTypes->hasDefinition('configurable_language')) {
        $config = $this->entityTypes->getStorage('configurable_language')->load($language);
        if ($config) {
          $dependencies['config'][] = $config->getConfigDependencyName();
        }
      }
    }
    return array_map(array_unique(...), $dependencies);
  }

}
