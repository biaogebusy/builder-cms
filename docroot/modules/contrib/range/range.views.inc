<?php

/**
 * @file
 * Provides views data for the range module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function range_field_views_data(FieldStorageConfigInterface $field_storage) {
  $data = views_field_default_views_data($field_storage);

  if (!empty($data)) {
    range_field_views_data_filter($data, $field_storage);
    range_field_views_data_argument($data, $field_storage);
  }

  return $data;
}

/**
 * Helper function. Returns misc data for a given field.
 *
 * This function returns misc field data, that is required by various Views
 * plugins. Data includes field label and field additional fields.
 *
 * @param \Drupal\field\FieldStorageConfigInterface $field_storage
 *   The field storage config entity.
 *
 * @return array
 *   Field data array containing:
 *     - Field label.
 *     - Field data table name.
 *     - Field data revision archive table name.
 *     - Array of field additional fields keyed by column name.
 */
function range_field_views_data_base(FieldStorageConfigInterface $field_storage) {
  $field_name = $field_storage->getName();
  // Check if the entity type supports revisions.
  $entity_manager = \Drupal::entityTypeManager();
  $entity_type_id = $field_storage->getTargetEntityTypeId();
  $entity_type = $entity_manager->getDefinition($entity_type_id);
  $supports_revisions = $entity_type->hasKey('revision') && $entity_type->getRevisionTable();
  // Get most used field label.
  $label = views_entity_field_label($entity_type_id, $field_name)[0];
  // Get additional fields.
  $storage = _views_field_get_entity_type_storage($field_storage);
  $table_mapping = $storage->getTableMapping();
  $add_field_names = ['delta', 'langcode', 'bundle'];
  $add_fields = array_combine($add_field_names, $add_field_names);
  foreach (array_keys($field_storage->getColumns()) as $column) {
    $add_fields[$column] = $table_mapping->getFieldColumnName($field_storage, $column);
  }

  return [
    $label,
    $table_mapping->getDedicatedDataTableName($field_storage),
    $supports_revisions ? $table_mapping->getDedicatedRevisionTableName($field_storage) : NULL,
    $add_fields,
  ];
}

/**
 * Provides range views filter data.
 *
 * @param array $data
 *   Views field data.
 * @param \Drupal\field\FieldStorageConfigInterface $field_storage
 *   The field storage config entity.
 */
function range_field_views_data_filter(array &$data, FieldStorageConfigInterface $field_storage) {
  [$label, $data_table_name, $revision_table_name, $additional_fields] = range_field_views_data_base($field_storage);

  $field_name = $field_storage->getName();
  $base = [
    'help' => t('Range contains (or does not contain) a value'),
    'id' => 'range',
    'field_name' => $field_name,
    'entity_type' => $field_storage->getTargetEntityTypeId(),
    'allow empty' => FALSE,
    'additional fields' => $additional_fields,
  ];

  $data[$data_table_name][$field_name]['filter'] = [
    'title' => t('@label range filter', ['@label' => $label]),
    'table' => $data_table_name,
  ] + $base;

  if ($revision_table_name) {
    $data[$revision_table_name]["$field_name-revision_id"]['filter'] = [
      'title' => t('@label range filter (historical data)', ['@label' => $label]),
      'table' => $revision_table_name,
    ] + $base;
  }
}

/**
 * Provides range views argument data.
 *
 * @param array $data
 *   Views data array.
 * @param \Drupal\field\FieldStorageConfigInterface $field_storage
 *   The field storage config entity.
 */
function range_field_views_data_argument(array &$data, FieldStorageConfigInterface $field_storage) {
  [$label, $data_table_name, $revision_table_name, $additional_fields] = range_field_views_data_base($field_storage);
  $field_name = $field_storage->getName();

  $base = [
    'help' => t('Range contains (or does not contain) a value'),
    'id' => 'range',
    'field_name' => $field_name,
    'entity_type' => $field_storage->getTargetEntityTypeId(),
    'empty field name' => t('- No value -'),
    'additional fields' => $additional_fields,
    // Temporarily make argument of a string type, until
    // https://www.drupal.org/node/2595025 ends up in core.
    'numeric' => FALSE,
    'break_phrase' => FALSE,
  ];

  $data[$data_table_name][$field_name]['argument'] = [
    'title' => t('@label range argument', ['@label' => $label]),
    'table' => $data_table_name,
  ] + $base;

  if ($revision_table_name) {
    $data[$revision_table_name]["$field_name-revision_id"]['argument'] = [
      'title' => t('@label range argument (historical data)', ['@label' => $label]),
      'table' => $revision_table_name,
    ] + $base;
  }
}
