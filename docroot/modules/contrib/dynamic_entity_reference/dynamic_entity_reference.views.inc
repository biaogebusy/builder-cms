<?php

/**
 * @file
 * Provides views data for the dynamic_entity_reference module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\dynamic_entity_reference\Hook\DynamicEntityReferenceViewsHooks;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
#[LegacyHook]
function dynamic_entity_reference_field_views_data(FieldStorageConfigInterface $field_storage) {
  return \Drupal::service(DynamicEntityReferenceViewsHooks::class)->fieldViewsData($field_storage);
}

/**
 * Implements hook_views_data().
 *
 * Adds relationships for dynamic_entity_reference base fields.
 *
 * @todo remove this when https://www.drupal.org/node/2337515 is in.
 */
#[LegacyHook]
function dynamic_entity_reference_views_data() {
  return \Drupal::service(DynamicEntityReferenceViewsHooks::class)->viewsData();
}
