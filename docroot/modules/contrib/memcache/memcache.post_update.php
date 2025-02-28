<?php

/**
 * @file
 * Memcache updates once other modules have made their own updates.
 */

/**
 * Invalidate the service container to force updates.
 */
function memcache_post_update_add_datetime() {
  // Reload the service container.
  $kernel = \Drupal::service('kernel');
  $kernel->invalidateContainer();
}
