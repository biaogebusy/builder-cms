<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;


use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the filter format entity type.
 *
 * @see \Drupal\filter\Entity\FilterFormat
 */
interface CollaborationAccessHandlerInterface {

  /**
   * Returns a collaboration permissions for a given user and filter format
   * to be used in CKEditor 5.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Current user.
   * @param string $filterFormat
   *   Filter format.
   *
   * @return array
   *   Permissions array in a CKEditor 5 format.
   */
  public function getCollaborationPermissionArray(AccountInterface $user, string $filterFormat): array;

  /**
   * Returns array with text formats and permissions for the user in a
   * CKEditor 5 format.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Current user.
   *
   * @return array
   *   Permissions for the all text formats.
   */
  public function getUserPermissionsForTextFormats(AccountInterface $user): array;

  /**
   * Get an array of user collaboration access for given filter format.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   Current user.
   * @param string $filterFormat
   *   Filter format name.
   *
   * @return array
   */
  public function getUserCollaborationAccess(AccountInterface $user, string $filterFormat): array;

  /**
   * Returns use permission name for provided filter format.
   *
   * @param string $filterFormat
   *   Filter format name.
   *
   * @return string
   *   Permission name.
   */
  public function filterFormatPermission(string $filterFormat): string;

}
