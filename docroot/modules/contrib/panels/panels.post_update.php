<?php

/**
 * @file
 * Post update functions for Panels.
 */

/**
 * Rebuild routes with the updated access requirements.
 */
function panels_post_update_update_route_requirements() {
  // Invalidates the container to be refreshed with the new access service.
  \Drupal::service('kernel')->invalidateContainer();
  // Rebuild routes to update requirements.
  \Drupal::service("router.builder")->rebuild();
}
