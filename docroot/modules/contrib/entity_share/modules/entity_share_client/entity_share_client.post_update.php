<?php

/**
 * @file
 * Post update functions for Entity Share Client.
 */

declare(strict_types = 1);

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;
use Drupal\entity_share_client\Entity\ImportConfig;

/**
 * Create a default import config to preserve 8.x-2.x behavior.
 */
function entity_share_client_post_update_create_default_import_config() {
  ImportConfig::create([
    'id' => 'default',
    'label' => t('Default'),
    'import_processor_settings' => [
      'block_field_block_content_importer' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'prepare_importable_entity_data' => 20,
        ],
      ],
      'changed_time' => [
        'weights' => [
          'process_entity' => 100,
        ],
      ],
      'default_data_processor' => [
        'weights' => [
          'is_entity_importable' => -10,
          'post_entity_save' => 0,
          'prepare_importable_entity_data' => -100,
        ],
      ],
      'entity_reference' => [
        'max_recursion_depth' => -1,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
      'physical_file' => [
        'weights' => [
          'process_entity' => 0,
        ],
      ],
    ],
  ])
    ->save();

  \Drupal::messenger()->addStatus(t('A default import config had been created. It is recommended to check it to ensure it matches your needs.'));
}

/**
 * Convert import status policy from int to string.
 */
function entity_share_client_post_update_convert_policy_to_string(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('entity_import_status');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('entity_import_status');
  if (empty($entity_type->getClass())) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition($entity_type->id());
  }
  if (empty($field_storage_definitions)) {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions('entity_import_status');
    $last_installed_schema_repository->setLastInstalledFieldStorageDefinitions('entity_import_status', $field_storage_definitions);
  }
  $field_storage_definitions['policy'] = BaseFieldDefinition::create('string')
    ->setName('policy')
    ->setTargetEntityTypeId('entity_import_status')
    ->setTargetBundle(NULL)
    ->setLabel(t('Policy'))
    ->setDescription(t('The import policy.'))
    ->setDefaultValue(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT);

  $definition_update_manager->updateFieldableEntityType($entity_type, $field_storage_definitions, $sandbox);

  return t("Import statuses' policy have been converted to string.");
}

/**
 * Set the new default policy value.
 */
function entity_share_client_post_update_set_new_default_policy() {
  $database = \Drupal::database();
  $database->update('entity_import_status')
    ->fields([
      'policy' => EntityImportStatusInterface::IMPORT_POLICY_DEFAULT,
    ])
    ->condition('policy', 0)
    ->execute();

  return t("Default Import statuses' policy have been updated.");
}

/**
 * Set new default settings on default data processor.
 */
function entity_share_client_post_update_update_default_data_processor_policy_settings() {
  /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface[] $import_configs */
  $import_configs = \Drupal::entityTypeManager()->getStorage('import_config')
    ->loadMultiple();

  foreach ($import_configs as $import_config) {
    $import_processor_settings = $import_config->get('import_processor_settings');
    if (isset($import_processor_settings['default_data_processor'])) {
      $import_processor_settings['default_data_processor']['policy'] = EntityImportStatusInterface::IMPORT_POLICY_DEFAULT;
      $import_processor_settings['default_data_processor']['update_policy'] = FALSE;
      $import_config->save();
    }
  }
}

/**
 * Set a default max size to import config.
 */
function entity_share_client_post_update_set_default_max_size() {
  /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface[] $import_configs */
  $import_configs = \Drupal::entityTypeManager()
    ->getStorage('import_config')
    ->loadMultiple();

  foreach ($import_configs as $import_config) {
    $import_config->set('import_maxsize', 50);
    $import_config->save();
  }
}
