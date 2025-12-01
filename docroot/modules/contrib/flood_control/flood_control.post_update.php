<?php

/**
 * @file
 * Post update functions for flood_control.
 */

/**
 * Remove contact module settings if the module is not in use.
 */
function flood_control_post_update_remove_unused_contact_module_settings(&$sandbox) {
  if (!\Drupal::moduleHandler()->moduleExists('contact')) {
    \Drupal::configFactory()->getEditable('contact.settings')->delete();
  }
}
