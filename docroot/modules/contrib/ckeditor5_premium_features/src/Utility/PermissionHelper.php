<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features\CollaborationPermissions;
use Drupal\ckeditor5_premium_features_collaboration\NonRealtimeCollaborationPermissions;
use Drupal\ckeditor5_premium_features_realtime_collaboration\RealtimeCollaborationPermissions;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Helper class for handling text format permissions.
 */
class PermissionHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler
  ) {
  }

  /**
   * Revokes collaboration permissions for given text format for all roles.
   *
   * @param array $filterFormats
   *   Array of filter format entities.
   */
  public function revokeCollaborationPermissions(array $filterFormats): void {
    if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_collaboration')) {
      $permissions = NonRealtimeCollaborationPermissions::getModulePermissions();
    }
    elseif ($this->moduleHandler->moduleExists('ckeditor5_premium_features_realtime_collaboration')) {
      $permissions = RealtimeCollaborationPermissions::getModulePermissions();
    }
    else {
      return;
    }
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      foreach($filterFormats as $filterFormat) {
        foreach ($permissions as $permission) {
          $permissionName = CollaborationPermissions::getPermissionName($filterFormat, $permission);
          if ($role->hasPermission($permissionName)) {
            $role->revokePermission($permissionName);
          }
        }
      }
      $role->save();
    }
  }

}
