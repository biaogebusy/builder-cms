<?php

/**
 * @file
 * Post update functions for Redis.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\user\Entity\Role;

/**
 * Update permissions for users with "access site reports" permission.
 */
function redis_post_update_add_report_permission(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'user_role', function (Role $role) {
    if ($role->hasPermission('access site reports')) {
      $role->grantPermission('access redis report');
      return TRUE;
    }
    return FALSE;
  });
}
