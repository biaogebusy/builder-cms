<?php

/**
 * @file
 * Provide views data for the Advanced Queue module.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\advancedqueue\Hook\AdvancedqueueViewsHooks;

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function advancedqueue_views_data() {
  return \Drupal::service(AdvancedqueueViewsHooks::class)->viewsData();
}
