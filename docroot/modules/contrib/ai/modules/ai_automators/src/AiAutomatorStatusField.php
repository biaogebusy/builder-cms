<?php

namespace Drupal\ai_automators;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Run one rule.
 */
class AiAutomatorStatusField {

  /**
   * Module dependency used for uninstall visibility and cleanup.
   */
  const MODULE_DEPENDENCY = 'ai_automators';

  /**
   * The defined field name.
   */
  const FIELD_NAME = 'ai_automator_status';

  /**
   * The four status.
   */
  const STATUS_PENDING = 'pending';
  const STATUS_PROCESSING = 'processing';
  const STATUS_FAILED = 'failed';
  const STATUS_FINISHED = 'finished';

  /**
   * The entity type manager.
   */
  protected EntityTypeManager $entityType;

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * Constructs a new AiAutomatorStatusField object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager, EntityFieldManagerInterface $fieldManager) {
    $this->entityType = $entityTypeManager;
    $this->fieldManager = $fieldManager;
  }

  /**
   * Modifies the status field if needed.
   *
   * @param string $entityType
   *   The entity type name.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   Success or not.
   */
  public function modifyStatusField($entityType, $bundle) {
    // Check if field is needed.
    $fieldIsNeeded = $this->automatorFieldNeeded($entityType, $bundle);

    // Check if field exists.
    $fieldExists = $this->automatorFieldExists($entityType, $bundle);

    if ($fieldIsNeeded && !$fieldExists) {
      // If it's needed but not exists.
      $this->addStatusField($entityType, $bundle);
    }
    elseif (!$fieldIsNeeded && $fieldExists) {
      // If it's not needed but exists.
      $this->removeStatusField($entityType, $bundle);
    }

    return TRUE;
  }

  /**
   * Checks if the status field is needed.
   *
   * @param string $entityType
   *   The entity type name.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   If the status field is needed or not.
   */
  protected function automatorFieldNeeded($entityType, $bundle) {
    return count($this->entityType->getStorage('ai_automator')->loadByProperties([
      'entity_type' => $entityType,
      'bundle' => $bundle,
    ])) > 0;
  }

  /**
   * Checks if the status field exists.
   *
   * @param string $entityType
   *   The entity type name.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   If the status field is exists or not.
   */
  protected function automatorFieldExists($entityType, $bundle) {
    $fields = $this->fieldManager->getFieldDefinitions($entityType, $bundle);
    return isset($fields[self::FIELD_NAME]);
  }

  /**
   * Adds the field to the config.
   *
   * @param string $entityType
   *   The entity type name.
   * @param string $bundle
   *   The bundle.
   */
  protected function addStatusField($entityType, $bundle) {
    // Create the storage only if needed.
    $fieldStorageLoader = $this->entityType->getStorage('field_storage_config');
    $query = $fieldStorageLoader->getQuery();
    $query->condition('entity_type', $entityType);
    $query->condition('id', $entityType . '.' . self::FIELD_NAME);

    // Check if a storage exists for the entity type.
    $storage = NULL;
    foreach ($query->execute() as $id) {
      $storage = $fieldStorageLoader->load($id);
    }

    // Otherwise create.
    if (!$storage) {
      $storage = FieldStorageConfig::create([
        'field_name' => self::FIELD_NAME,
        'entity_type' => $entityType,
        'type' => 'list_string',
        'module' => 'options',
        'settings' => [
          'allowed_values' => [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_FINISHED => 'Finished',
          ],
        ],
        'locked' => TRUE,
        'cardinality' => 1,
        'translatable' => FALSE,
        'dependencies' => [
          'enforced' => [
            'module' => [self::MODULE_DEPENDENCY],
          ],
        ],
      ]);
      $storage->save();
    }
    else {
      $this->ensureModuleDependency($storage);
    }

    $config = FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'label' => 'AI Automator Status',
      'default_value' => [
        [
          'value' => self::STATUS_PENDING,
        ],
      ],
      'required' => TRUE,
      'dependencies' => [
        'enforced' => [
          'module' => [self::MODULE_DEPENDENCY],
        ],
      ],
    ]);
    $config->save();
  }

  /**
   * Removes the field to the config.
   *
   * @param string $entityType
   *   The entity type name.
   * @param string $bundle
   *   The bundle.
   */
  protected function removeStatusField($entityType, $bundle) {
    // Load the config to remove.
    $config = FieldConfig::loadByName($entityType, $bundle, self::FIELD_NAME);
    if (empty($config)) {
      return;
    }
    // Remove it.
    $config->delete();
    // Clear full entity definition cache.
    $this->entityType->clearCachedDefinitions();
    // Check if another bundle storage exists for the config.
    $query = $this->entityType->getStorage('field_config')->getQuery();
    $query->condition('entity_type', $entityType);
    if (!$query->count()->execute()) {
      $storage = FieldStorageConfig::loadByName($entityType, self::FIELD_NAME);
      // Storage might auto-delete and not exist.
      if ($storage) {
        $storage->delete();
      }
    }
  }

  /**
   * Adds an enforced ai_automators module dependency to a config entity.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $configEntity
   *   The config entity to adjust.
   */
  protected function ensureModuleDependency(ConfigEntityInterface $configEntity): void {
    $dependencies = $configEntity->get('dependencies') ?? [];
    $modules = $dependencies['enforced']['module'] ?? [];
    if (!in_array(self::MODULE_DEPENDENCY, $modules, TRUE)) {
      $modules[] = self::MODULE_DEPENDENCY;
      sort($modules);
      $dependencies['enforced']['module'] = $modules;
      $configEntity->set('dependencies', $dependencies);
      $configEntity->save();
    }
  }

}
