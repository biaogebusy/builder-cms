<?php

/**
 * @file
 * Post update functions for Content Lock module.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * Updates views cache settings for view displaying content lock information.
 */
function content_lock_post_update_fixing_views_caching(array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    // Re-save all views with a dependency on the Content lock module.
    return in_array('content_lock', $view->getDependencies()['module'] ?? [], TRUE);
  });
}
