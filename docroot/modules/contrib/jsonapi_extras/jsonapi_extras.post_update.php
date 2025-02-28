<?php

/**
 * @file
 * Contains post-update hooks.
 */

/**
 * Implements hook_post_update_NAME().
 */
function jsonapi_extras_post_update_set_default_value_for_default_disabled() {
  \Drupal::configFactory()
    ->getEditable('jsonapi_extras.settings')
    ->set('default_disabled', FALSE)
    ->save(TRUE);
}
