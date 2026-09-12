<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the text format which uses realtime
 * collaboration plugins.
 */
class CollaborationAccessHandler implements CollaborationAccessHandlerInterface {

  /**
   * Constructs a new CollaborationAccessHandler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function getCollaborationPermissionArray(AccountInterface $user, string $filterFormat): array {
    $filterFormatPermission = $this->filterFormatPermission($filterFormat);
    // Read permissions required for RTC.
    $collaborationPermissions = ['document:read', 'comment:read'];
    if ($user->hasPermission(
      $filterFormatPermission . CollaborationPermissions::COMMENTS_ADMIN)) {
      $collaborationPermissions[] = 'comment:admin';
      $collaborationPermissions[] = 'comment:write';
    }
    elseif ($user->hasPermission(
      $filterFormatPermission . CollaborationPermissions::COMMENTS_WRITE)) {
      $collaborationPermissions[] = 'comment:write';
    }

    if ($user->hasPermission(
      $filterFormatPermission . CollaborationPermissions::DOCUMENT_WRITE)) {
      $collaborationPermissions[] = 'document:write';
      $collaborationPermissions[] = 'document:admin';
    }

    return $collaborationPermissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserPermissionsForTextFormats(AccountInterface $user): array {
    $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
    $permissions = [];
    foreach ($formats as $format) {
      $formatId = $format->id();
      $permissions[$formatId] = $this->getCollaborationPermissionArray($user, $formatId);
    }
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCollaborationAccess(AccountInterface $user, string $filterFormat): array {
    $filterFormatPermission = $this->filterFormatPermission($filterFormat);

    return [
      'document_write' => $user->hasPermission($filterFormatPermission . CollaborationPermissions::DOCUMENT_WRITE),
      'comment_write' => $user->hasPermission($filterFormatPermission . CollaborationPermissions::COMMENTS_WRITE),
      'comment_admin' => $user->hasPermission($filterFormatPermission . CollaborationPermissions::COMMENTS_ADMIN),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filterFormatPermission(string $filterFormat): string {
    return 'use text format ' . $filterFormat . ' with collaboration ';
  }

}
