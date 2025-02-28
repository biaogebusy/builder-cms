<?php

namespace Drupal\conflict;

use Drupal\Component\Utility\SortArray;
use Drupal\conflict\Annotation\FieldComparator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class FieldComparatorManager extends DefaultPluginManager implements FieldComparatorManagerInterface {

  /**
   * The field comparators.
   *
   * @var array
   */
  protected $fieldComparators;

  /**
   * The ordered field comparators for a specific field.
   *
   * @var array
   */
  protected $orderedFieldComparators;

  /**
   * Constructs a new FieldComparatorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Conflict/FieldComparator',
      $namespaces,
      $module_handler,
      FieldComparatorInterface::class,
      FieldComparator::class
    );

    $this->setCacheBackend($cache_backend, 'conflict.field_comparator.plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getConflictType(FieldItemListInterface $local, FieldItemListInterface $server, FieldItemListInterface $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name) {
    // Iterate from the most specific to the most general comparator.
    foreach ($this->getOrderedFieldComparators($entity_type_id, $bundle, $field_type, $field_name) as &$comparator) {
      /** @var \Drupal\conflict\FieldComparatorInterface $comparator */
      if (!is_object($comparator)) {
        $comparator = $this->createInstance($comparator);
      }
      if ($comparator::isApplicable($local->getFieldDefinition())) {
        $conflict_type = $comparator->getConflictType($local, $server, $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name);
        if ($conflict_type) {
          return $conflict_type;
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasChanged(FieldItemListInterface $items_a, FieldItemListInterface $items_b, $langcode, $entity_type_id, $bundle, $field_type, $field_name) {
    // Iterate from the most specific to the most general comparator.
    foreach ($this->getOrderedFieldComparators($entity_type_id, $bundle, $field_type, $field_name) as &$comparator) {
      /** @var \Drupal\conflict\FieldComparatorInterface $comparator */
      if (!is_object($comparator)) {
        $comparator = $this->createInstance($comparator);
      }
      if ($comparator::isApplicable($items_a->getFieldDefinition())) {
        $result = $comparator->hasChanged($items_a, $items_b, $langcode, $entity_type_id, $bundle, $field_type, $field_name);
        if (is_bool($result)) {
          return $result;
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns the field comparators.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The entity bundle ID.
   * @param string $field_type
   *   The field type.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The field comparators.
   */
  protected function getOrderedFieldComparators($entity_type_id, $bundle, $field_type, $field_name) {
    if (isset($this->orderedFieldComparators[$entity_type_id][$bundle][$field_type][$field_name])) {
      return $this->orderedFieldComparators[$entity_type_id][$bundle][$field_type][$field_name];
    }

    $this->initFieldComparators();

    $generic = FieldComparatorInterface::APPLIES_TO_ALL;
    $comparators = [];

    // Entity type - specific.
    // Bundle      - specific.
    // Field type  - specific.
    // Field name  - specific.
    $comparators += $this->fieldComparators[$entity_type_id][$bundle][$field_type][$field_name]['comparators'] ?? [];
    // Entity type - specific.
    // Bundle      - specific.
    // Field type  - specific.
    // Field name  - all.
    $comparators += $this->fieldComparators[$entity_type_id][$bundle][$field_type][$generic]['comparators'] ?? [];
    // Entity type - specific.
    // Bundle      - all.
    // Field type  - specific.
    // Field name  - all.
    $comparators += $this->fieldComparators[$entity_type_id][$generic][$field_type][$generic]['comparators'] ?? [];
    // Entity type - specific.
    // Bundle      - all.
    // Field type  - specific.
    // Field name  - specific.
    $comparators += $this->fieldComparators[$entity_type_id][$generic][$field_type][$field_name]['comparators'] ?? [];
    // Entity type - specific.
    // Bundle      - all.
    // Field type  - all.
    // Field name  - all.
    $comparators += $this->fieldComparators[$entity_type_id][$generic][$generic][$generic]['comparators'] ?? [];
    // Entity type - all.
    // Bundle      - all.
    // Field type  - specific.
    // Field name  - all.
    $comparators += $this->fieldComparators[$generic][$generic][$field_type][$generic]['comparators'] ?? [];
    // Entity type - all.
    // Bundle      - all.
    // Field type  - all.
    // Field name  - all.
    $comparators += $this->fieldComparators[$generic][$generic][$generic][$generic]['comparators'] ?? [];

    if (empty($comparators)) {
      throw new \Exception('There are no field comparators available.');
    }

    $this->orderedFieldComparators[$entity_type_id][$bundle][$field_type][$field_name] = $comparators;
    return $comparators;
  }

  /**
   * Initializes the field comparators.
   */
  protected function initFieldComparators() {
    if (!isset($this->fieldComparators)) {
      $this->fieldComparators = [];
      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        $entity_type_id = $definition['entity_type_id'];
        $bundle = $definition['bundle'];
        $field_type = $definition['field_type'];
        $field_name = $definition['field_name'];

        if (!isset($this->fieldComparators[$entity_type_id][$bundle][$field_type][$field_name]['comparators'])) {
          $this->fieldComparators[$entity_type_id][$bundle][$field_type][$field_name]['comparators'] = [];
        }
        $this->fieldComparators[$entity_type_id][$bundle][$field_type][$field_name]['comparators'][] = $plugin_id;
      }
    }
  }

  /**
   * Finds plugin definitions.
   *
   * @return array
   *   List of definitions to store in cache.
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);
    $definitions = array_reverse($definitions, TRUE);
    return $definitions;
  }

}
