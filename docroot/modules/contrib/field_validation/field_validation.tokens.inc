<?php

/**
 * @file
 * Builds placeholder replacement tokens for field validation.
 *
 * This file handles tokens for the 'current-field' tokens.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function field_validation_token_info() {
  $types['current-field'] = [
    'name' => t("Current field"),
    'description' => t("Tokens related to the current field."),
  ];

  // Site-wide global tokens.
  $current_field['entity-type'] = [
    'name' => t("Entity type"),
    'description' => t("The entity type that current field belong."),
  ];
  $current_field['bundle'] = [
    'name' => t("Bundle"),
    'description' => t("The bundle that current field belong."),
  ];
  $current_field['field-name'] = [
    'name' => t("Field name"),
    'description' => t("The name of current field."),
  ];
  $current_field['value'] = [
    'name' => t("Value"),
    'description' => t("The property value of current field."),
  ];

  return [
    'types' => $types,
    'tokens' => [
      'current-field' => $current_field,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function field_validation_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {

  $replacements = [];

  if ($type == 'current-field' && !empty($data['current_field'])) {
    $current_field = $data['current_field'];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'entity-type':
          $replacements[$original] = $current_field['entity_type'] ?? $original;
          break;

        case 'bundle':
          $replacements[$original] = $current_field['bundle'] ?? $original;
          break;

        case 'field-name':
          $replacements[$original] = $current_field['field_name'] ?? $original;
          break;

        case 'value':
          $replacements[$original] = $current_field['value'] ?? $original;
          break;
      }
    }
  }

  return $replacements;
}
