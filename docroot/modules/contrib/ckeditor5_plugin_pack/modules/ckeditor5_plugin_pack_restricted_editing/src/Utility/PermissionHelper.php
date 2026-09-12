<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_restricted_editing\Utility;

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
  public function revokeRestrictedEditingPermission(array $filterFormats): void {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      foreach($filterFormats as $filterFormat) {
        $permissionName = $filterFormat->id() . ' bypass restricted editing';
        if ($role->hasPermission($permissionName)) {
          $role->revokePermission($permissionName);
          $role->save();
        }
      }
    }
  }

}
