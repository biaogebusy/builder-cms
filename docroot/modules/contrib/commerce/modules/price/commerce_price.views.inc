<?php

/**
 * @file
 * Provide Views data for the Price module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 *
 * Views integration for price fields.
 */
function commerce_price_field_views_data(FieldStorageConfigInterface $field_storage) {
  if (\Drupal::hasService('views.field_data_provider')) {
    $data = \Drupal::service('views.field_data_provider')->defaultFieldImplementation($field_storage);
  }
  else {
    $deprecated_function = 'views_field_default_views_data';
    $data = $deprecated_function($field_storage);
  }
  $field_name = $field_storage->getName();
  foreach ($data as $table_name => $table_data) {
    if (isset($table_data[$field_name])) {
      $data[$table_name][$field_name . '_number']['field'] = [
        'id' => 'numeric',
        'field_name' => $table_data[$field_name]['field']['field_name'],
        'entity_type' => $table_data[$field_name]['field']['entity_type'],
        'label' => t('number from @field_name', ['@field_name' => $field_name]),
      ];
      $data[$table_name][$field_name . '_currency_code']['field'] = [
        'id' => 'standard',
        'field_name' => $table_data[$field_name]['field']['field_name'],
        'entity_type' => $table_data[$field_name]['field']['entity_type'],
        'label' => t('currency from @field_name', ['@field_name' => $field_name]),
      ];
      $data[$table_name][$field_name . '_currency_code']['filter']['id'] = 'commerce_currency';
    }
  }
  return $data;
}
