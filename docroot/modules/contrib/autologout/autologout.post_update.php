<?php

/**
 * @file
 * Perform updates once other modules have made their own updates.
 */

/**
 * Post update hook for all changes between 8.x-1.3 and 8.x-1.4.
 */
function autologout_post_update_8014() {

  // Issue #3219414: Add a disable checkbox for autologout.
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('autologout.settings');
  $disable_button = $config->get('enabled');
  if ($disable_button === NULL) {
    $config->set('enabled', TRUE);
  }

  // Issue #3258234, #3293627: Ensure whitelisted ip address value is set.
  $whitelist = $config->get('whitelisted_ip_addresses');
  if ($whitelist === NULL) {
    $config->set('whitelisted_ip_addresses', '');
  }

  // Issue #3284804: Inactivity Message Type Missing.
  if (empty($config->get('inactivity_message_type'))) {
    $config->set('inactivity_message_type', 'status');
  }

  // Issue 3205591: Add modal_width config default value.
  if (empty($config->get('modal_width'))) {
    $config->set('modal_width', 450);
  }

  $config->save(TRUE);

  // Issue #3101732: Flush caches due to service signature changes.
  drupal_flush_all_caches();
}

/**
 * Post update hook to set include destination to true.
 */
function autologout_post_update_9001() {
  // Issue #3195164: Option to disable destination.
  $config_factory = \Drupal::configFactory();
  $config = $config_factory->getEditable('autologout.settings');
  $includeDestination = $config->get('include_destination');
  if (!$includeDestination) {
    $config->set('include_destination', TRUE);
    $config->save(TRUE);
  }
}

/**
 * Make Drupal 10 warning message more user friendly.
 */
function autologout_post_update_9502(&$sandbox) {
  // Issue #3390606: Make Drupal 10 warning message more user friendly.
  $configFactory = \Drupal::configFactory();
  $config = $configFactory->getEditable('autologout.settings');
  $message = $config->get('message');
  if ($message === 'Your session is about to expire. Do you want to reset it?') {
    $config->set('message', 'We are about to log you out for inactivity. If we do, you will lose any unsaved work. Do you need more time?');
  }
  $config->save(TRUE);
}
