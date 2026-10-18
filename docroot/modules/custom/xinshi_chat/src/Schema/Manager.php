<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Schema;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/** Applies an explicit structure version without replacing content or site configuration. */
final class Manager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fields,
    private readonly ConfigFactoryInterface $config,
    private readonly EntityDisplayRepositoryInterface $displays,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /** Inspect all conflicts before any configuration is written. */
  public function inspect(int $version = Definition::CURRENT): array {
    $definitions = Definition::fields($version);
    $changes = $conflicts = [];
    $protocol = $this->config->get('xinshi_ai_reference.settings')->get('storage_version') ?? 1;
    if (!in_array($protocol, [1, 2, 3], TRUE)) {
      $conflicts[] = ['path' => 'xinshi_ai_reference.settings.storage_version',
        'reason' => 'Unknown Chat storage version.'];
    }
    if ($protocol !== Definition::STORAGE_VERSION && $this->hasContent()) {
      $conflicts[] = ['path' => 'xinshi_ai_reference.settings.storage_version',
        'reason' => 'Migrate existing Chat data to storage version 3 before adoption.'];
    }
    foreach ($definitions as $bundle => $items) {
      $type = $this->entities->getStorage('node_type')->load($bundle);
      if (!$type) {
        $changes[] = ['kind' => 'bundle', 'bundle' => $bundle];
      }
      elseif (!$type->shouldCreateNewRevision()) {
        $conflicts[] = ['path' => 'node.type.' . $bundle, 'reason' => 'New revisions must be enabled.'];
      }
      if ($type && !in_array('xinshi_chat', $type->get('dependencies')['enforced']['module'] ?? [], TRUE)) {
        $changes[] = ['kind' => 'ownership', 'bundle' => $bundle];
      }
      $actual = $type ? $this->fields->getFieldDefinitions('node', $bundle) : [];
      $resource = $type ? $this->resources->get('node', $bundle) : NULL;
      if ($type && (!$resource || $resource->isInternal())) {
        $conflicts[] = ['path' => 'jsonapi.' . $bundle, 'reason' => 'Chat JSON:API resource is disabled.'];
      }
      foreach ($actual as $name => $field) {
        if (!$field->getFieldStorageDefinition()->isBaseField() && !isset($items[$name])) {
          $conflicts[] = ['path' => 'field.field.node.' . $bundle . '.' . $name,
            'reason' => 'Unmanaged Chat field; use an explicit extension or retirement migration.'];
        }
      }
      foreach ($items as $name => $expected) {
        $storage = $this->entities->getStorage('field_storage_config')->load('node.' . $name);
        $field = $this->entities->getStorage('field_config')->load('node.' . $bundle . '.' . $name);
        $path = 'field.storage.node.' . $name;
        if (!$storage) {
          $changes[] = ['kind' => 'storage', 'bundle' => $bundle, 'field' => $name];
        }
        else {
          $other = array_diff(array_values($storage->getBundles()), [$bundle]);
          $valid = $storage->getType() === $expected['type'] && $storage->isRevisionable()
            && (!$expected['exclusive'] || !$other)
            && (!array_key_exists('storage_translatable', $expected)
              || $storage->isTranslatable() === $expected['storage_translatable']);
          $updates = [];
          if ($storage->getCardinality() !== $expected['cardinality']) {
            $widen = $expected['cardinality'] === -1 ||
              ($storage->getCardinality() !== -1 && $expected['cardinality'] > $storage->getCardinality());
            $valid = $valid && $widen && !$other;
            $updates['cardinality'] = $expected['cardinality'];
          }
          foreach ($expected['settings'] as $key => $value) {
            if ($storage->getSetting($key) !== $value) {
              // Core cannot change populated SQL columns through FieldStorageConfig::save().
              $valid = $valid && $key === 'max_length' && $value > $storage->getSetting($key)
                && !$other && !$storage->hasData();
              $updates['settings'][$key] = $value;
            }
          }
          if (!$valid) {
            $conflicts[] = ['path' => $path, 'reason' => 'Incompatible or shared field storage.'];
          }
          elseif ($updates) {
            $changes[] = ['kind' => 'storage_update', 'bundle' => $bundle,
              'field' => $name, 'updates' => $updates];
          }
        }
        if (!$field) {
          $changes[] = ['kind' => 'field', 'bundle' => $bundle, 'field' => $name];
          continue;
        }
        if (!$resource || !$resource->isFieldEnabled($name) || $resource->getPublicName($name) !== $name) {
          $conflicts[] = ['path' => 'jsonapi.' . $bundle . '.' . $name,
            'reason' => 'Chat requires its original public field name and an enabled field.'];
        }
        $valid = $field->isRequired() === $expected['required']
          && $field->isTranslatable() === $expected['translatable'];
        foreach ($expected['field_settings'] as $key => $value) {
          // Preserve unrelated reference-selection settings chosen by the site.
          $actual_setting = $field->getSetting($key);
          $valid = $valid && ($key === 'handler_settings'
            ? array_values($actual_setting['target_bundles'] ?? []) === array_values($value['target_bundles'])
            : $actual_setting === $value);
        }
        if (array_key_exists('default_value', $expected)) {
          $valid = $valid && $field->getDefaultValueLiteral() === $expected['default_value']
            && $field->getDefaultValueCallback() === $expected['default_value_callback'];
        }
        if (!$valid) {
          $conflicts[] = ['path' => 'field.field.node.' . $bundle . '.' . $name,
            'reason' => 'Field constraints or defaults require an explicit migration.'];
        }
      }
    }
    return ['target_version' => $version,
      'installed_version' => $this->config->get('xinshi_chat.settings')->get('schema_version') ?? 0,
      'storage_version' => $protocol, 'changes' => $changes, 'conflicts' => $conflicts,
      'matches' => !$changes && !$conflicts && $protocol === Definition::STORAGE_VERSION];
  }

  /** Create or adopt a complete version; retrying never recreates existing entities. */
  public function apply(int $version = Definition::CURRENT): array {
    if (($this->config->get('xinshi_chat.settings')->get('schema_version') ?? 0) > $version) {
      throw new \RuntimeException('Chat structure cannot be downgraded by an installation update.');
    }
    if (!$this->lock->acquire('xinshi_chat.schema', 900)) {
      throw new \RuntimeException('Another Chat structure update is running.');
    }
    try {
      $report = $this->inspect($version);
      if ($report['conflicts']) {
        throw new \RuntimeException('Chat structure conflicts: ' . json_encode($report['conflicts'], JSON_THROW_ON_ERROR));
      }
      $definitions = Definition::fields($version);
      foreach ($definitions as $bundle => $items) {
        $type = $this->entities->getStorage('node_type')->load($bundle);
        if (!$type) {
          $type = NodeType::create(['type' => $bundle,
            'name' => $bundle === 'conversation' ? 'Conversation' : 'Chat message',
            'new_revision' => TRUE, 'display_submitted' => FALSE]);
        }
        $dependencies = $type->get('dependencies');
        $modules = $dependencies['enforced']['module'] ?? [];
        if ($type->isNew() || !in_array('xinshi_chat', $modules, TRUE)) {
          $dependencies['enforced']['module'] = array_values(array_unique([...$modules, 'xinshi_chat']));
          $type->set('dependencies', $dependencies)->save();
        }
        foreach ($items as $name => $expected) {
          $storage = FieldStorageConfig::loadByName('node', $name);
          if (!$storage) {
            FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => $name,
              'type' => $expected['type'], 'cardinality' => $expected['cardinality'],
              'settings' => $expected['settings'], 'translatable' => FALSE])->save();
          }
          else {
            foreach ($report['changes'] as $change) {
              if ($change['kind'] !== 'storage_update' || $change['field'] !== $name) {
                continue;
              }
              if (isset($change['updates']['cardinality'])) {
                $storage->setCardinality($change['updates']['cardinality']);
              }
              foreach ($change['updates']['settings'] ?? [] as $key => $value) {
                $storage->setSetting($key, $value);
              }
              $storage->save();
            }
          }
          if (!FieldConfig::loadByName('node', $bundle, $name)) {
            FieldConfig::create(['entity_type' => 'node', 'bundle' => $bundle,
              'field_name' => $name, 'label' => $name, 'required' => $expected['required'],
              'translatable' => $expected['translatable'], 'settings' => $expected['field_settings'],
              'default_value' => [], 'default_value_callback' => ''])->save();
          }
        }
        // Defaults are created once; upgrades retain the site's presentation choices.
        $form = $this->displays->getFormDisplay('node', $bundle);
        if ($form->isNew()) {
          $form->setComponent('title', ['type' => 'string_textfield'])->save();
        }
        $view = $this->displays->getViewDisplay('node', $bundle);
        if ($view->isNew()) {
          foreach (array_keys($items) as $name) {
            $view->removeComponent($name);
          }
          $view->save();
        }
      }
      $this->fields->clearCachedFieldDefinitions();
      if ($report['storage_version'] !== Definition::STORAGE_VERSION) {
        $this->config->getEditable('xinshi_ai_reference.settings')
          ->set('storage_version', Definition::STORAGE_VERSION)->save();
      }
      $result = $this->inspect($version);
      if (!$result['matches']) {
        throw new \RuntimeException('Chat structure verification failed; retain the site and retry the update.');
      }
      if ($this->config->get('xinshi_chat.settings')->get('schema_version') !== $version) {
        $this->config->getEditable('xinshi_chat.settings')->set('schema_version', $version)->save();
      }
      return $this->inspect($version);
    }
    finally {
      $this->lock->release('xinshi_chat.schema');
    }
  }

  /** Include all authors and publication states; this is an administrative lifecycle check. */
  public function hasContent(): bool {
    return $this->database->schema()->tableExists('node') && (bool) $this->database
      ->select('node', 'n')->condition('type', Definition::BUNDLES, 'IN')
      ->range(0, 1)->countQuery()->execute()->fetchField();
  }

  /** Retire an explicitly named obsolete field only after its revision data is migrated. */
  public function retireField(string $bundle, string $name, array $previous_definition): void {
    if (!$this->lock->acquire('xinshi_chat.schema', 900)) {
      throw new \RuntimeException('Another Chat structure update is running.');
    }
    try {
      $this->retireEmptyField($bundle, $name, $previous_definition);
    }
    finally {
      $this->lock->release('xinshi_chat.schema');
    }
  }

  /** Require a quiescent site; background writers must also be stopped by deployment. */
  private function retireEmptyField(string $bundle, string $name, array $previous_definition): void {
    if ($this->config->get('xinshi_chat.settings')->get('enabled')) {
      throw new \RuntimeException('Disable Chat and stop its other writers before field retirement.');
    }
    if (!in_array($bundle, Definition::BUNDLES, TRUE) || isset(Definition::fields()[$bundle][$name])) {
      throw new \InvalidArgumentException('Only an explicitly retired Chat field can be removed.');
    }
    $field = FieldConfig::loadByName('node', $bundle, $name);
    if (!$field) {
      return;
    }
    $storage = $field->getFieldStorageDefinition();
    if ($storage->getType() !== ($previous_definition['type'] ?? NULL)
      || $storage->getCardinality() !== ($previous_definition['cardinality'] ?? 1)) {
      throw new \RuntimeException('Retired field differs from the migration contract.');
    }
    $mapping = $this->entities->getStorage('node')->getTableMapping();
    foreach ([$mapping->getDedicatedDataTableName($storage), $mapping->getDedicatedRevisionTableName($storage)] as $table) {
      if (!$this->database->schema()->tableExists($table)) {
        continue;
      }
      $query = $this->database->select($table, 'f')->condition('bundle', $bundle);
      $values = $query->orConditionGroup();
      foreach (array_keys($storage->getColumns()) as $column) {
        $values->isNotNull($mapping->getFieldColumnName($storage, $column));
      }
      if ($query->condition($values)->range(0, 1)->countQuery()->execute()->fetchField()) {
        throw new \RuntimeException('Migrate and verify all current and revision field data before retirement.');
      }
    }
    // Drupal deletes storage only when no other bundle still uses it.
    $field->delete();
  }

}
