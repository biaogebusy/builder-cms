<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_entity_tools\Entity\EntityToolResource;

/** Projects explicitly select fields; Drupal still checks each actual entity. */
final class ResourceCatalog {

  public const TYPES = ['string', 'string_long', 'integer', 'boolean', 'timestamp',
    'list_string', 'list_integer', 'text', 'text_long', 'text_with_summary'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fields,
    private readonly AccountInterface $account,
    private readonly LanguageManagerInterface $languages,
  ) {}

  /** Reject system fields, computed values and unsupported field structures. */
  public function validate(EntityToolResource $resource): void {
    if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', (string) $resource->id()) ||
      !is_string($resource->label()) || !$resource->label() || strlen($resource->label()) > 255 ||
      !is_int($resource->get('version')) || $resource->get('version') < 1 ||
      $resource->get('version') > 2147483647 ||
      !is_string($resource->get('bundle')) || !$this->entities->getStorage('node_type')->load($resource->get('bundle'))) {
      throw new \InvalidArgumentException('Invalid resource identity, bundle or version.');
    }
    foreach (['fields', 'write_fields', 'languages'] as $key) {
      $values = $resource->get($key);
      if (!is_array($values) || !array_is_list($values) || count($values) > 30 ||
        array_filter($values, fn($v) => !is_string($v)) || count(array_unique($values)) !== count($values)) {
        throw new \InvalidArgumentException('Resource fields and languages must be unique string lists.');
      }
    }
    if (!in_array('title', $resource->get('fields'), TRUE) || !$resource->get('languages') ||
      array_diff($resource->get('write_fields'), $resource->get('fields')) ||
      array_diff($resource->get('languages'), array_keys($this->languages->getLanguages())) ||
      !is_bool($resource->get('allow_create')) || !is_bool($resource->get('allow_update')) ||
      ($resource->get('allow_create') && !in_array('title', $resource->get('write_fields'), TRUE))) {
      throw new \InvalidArgumentException('Select readable title, explicit languages and writable field subsets.');
    }
    $definitions = $this->fields->getFieldDefinitions('node', $resource->get('bundle'));
    foreach ($resource->get('fields') as $name) {
      $field = $definitions[$name] ?? NULL;
      if (!$field || ($field->getFieldStorageDefinition()->isBaseField() && $name !== 'title') ||
        $field->isComputed() || $field->isReadOnly() ||
        $field->getFieldStorageDefinition()->getCardinality() !== 1 || !in_array($field->getType(), self::TYPES, TRUE) ||
        $field->getSetting('allowed_values_function')) {
        throw new \InvalidArgumentException('Unsupported field: ' . $name);
      }
    }
    if (!is_string($resource->get('text_format')) ||
      !$this->entities->getStorage('filter_format')->load($resource->get('text_format'))) {
      throw new \InvalidArgumentException('An existing text format is required.');
    }
  }

  /** Capability discovery is bound to the effective OAuth account, not its full user entity. */
  public function capabilities(): array {
    $allowed = $this->account->isAuthenticated() && $this->account->hasPermission('use entity tools') &&
      $this->entities->getStorage('user')->loadUnchanged($this->account->id())?->isActive();
    return ['uid' => (string) $this->account->id(), 'permissions' => !$allowed ? [] :
      ($this->account->hasPermission('write entity tools') ? ['entities.read', 'entities.write'] : ['entities.read'])];
  }

  /** Resolve only a published resource definition; it does not grant entity access. */
  public function get(string $id, ?int $version = NULL): EntityToolResource {
    if (!in_array('entities.read', $this->capabilities()['permissions'], TRUE)) {
      throw new EntityToolException('permission_denied', 403);
    }
    $resource = $this->entities->getStorage('entity_tool_resource')->load($id);
    if (!$resource || !$resource->status()) {
      throw new EntityToolException('resource_unavailable', 404);
    }
    $this->validate($resource);
    if ($version !== NULL && $resource->get('version') !== $version) {
      throw new EntityToolException('version_conflict', 409);
    }
    return $resource;
  }

  /** Bounded summaries let a model choose a resource before loading its field definitions. */
  public function discover(): array {
    if (!in_array('entities.read', $this->capabilities()['permissions'], TRUE)) {
      throw new EntityToolException('permission_denied', 403);
    }
    $resources = [];
    $storage = $this->entities->getStorage('entity_tool_resource');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('status', TRUE)->sort('id')->range(0, 101)->execute();
    if (count($ids) > 100) {
      throw new EntityToolException('resource_limit', 422);
    }
    foreach ($storage->loadMultiple($ids) as $resource) {
      $this->validate($resource);
      $resources[] = $this->summary($resource);
    }
    return ['resources' => $resources];
  }

  /** Field metadata advertises supported shapes; instance-dependent access is checked later. */
  public function describe(string $id): array {
    $resource = $this->get($id);
    $definitions = $this->fields->getFieldDefinitions('node', $resource->get('bundle'));
    $fields = [];
    foreach ($resource->get('fields') as $name) {
      $field = $definitions[$name];
      $type = $field->getType();
      $allowed = $field->getSetting('allowed_values') ?? [];
      $fields[] = ['name' => $name, 'label' => (string) $field->getLabel(), 'type' => $type,
        'required' => $field->isRequired(), 'writable' => in_array($name, $resource->get('write_fields'), TRUE),
        'maxLength' => $field->getSetting('max_length') ?: NULL,
        'allowedValues' => array_map(fn($v) => $type === 'list_integer' ? (int) $v : (string) $v, array_keys($allowed)),
        'format' => str_starts_with($type, 'text') ? $resource->get('text_format') : NULL];
    }
    return $this->summary($resource) + ['entityType' => 'node', 'fields' => $fields,
      'limits' => ['pageSize' => 25, 'scanSize' => 500, 'filters' => 5],
      'filterOperators' => ['eq', 'contains'], 'writes' => 'single_language_unpublished_default_revision'];
  }

  /** Reject mismatched predicates instead of silently presenting an empty result. */
  public function validateFilters(EntityToolResource $resource, array $filters): void {
    $definitions = $this->fields->getFieldDefinitions('node', $resource->get('bundle'));
    foreach ($filters as $filter) {
      $type = $definitions[$filter->field]->getType();
      $valid = match ($type) {
        'integer', 'list_integer', 'timestamp' => $filter->operator === 'eq' &&
          is_int($filter->value) && abs($filter->value) <= 9007199254740991,
        'boolean' => $filter->operator === 'eq' && is_bool($filter->value),
        default => is_string($filter->value),
      };
      if (!$valid) {
        throw new EntityToolException('invalid_input', 400);
      }
    }
  }

  private function summary(EntityToolResource $resource): array {
    return ['resourceId' => $resource->id(), 'resourceVersion' => $resource->get('version'),
      'label' => $resource->label(), 'languages' => $resource->get('languages'),
      'canCreateDraft' => $resource->get('allow_create') && $this->account->hasPermission('write entity tools') &&
        $this->entities->getAccessControlHandler('node')->createAccess($resource->get('bundle'), $this->account),
      'canUpdateDraft' => $resource->get('allow_update') && $this->account->hasPermission('write entity tools')];
  }

}
