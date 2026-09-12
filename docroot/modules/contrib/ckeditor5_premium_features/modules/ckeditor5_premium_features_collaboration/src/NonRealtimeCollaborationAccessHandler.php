<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration;

use Drupal\ckeditor5_premium_features\CollaborationAccessHandler;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the text format which uses non-realtime
 * collaboration plugins.
 */
class NonRealtimeCollaborationAccessHandler extends CollaborationAccessHandler {

  /**
   * {@inheritdoc}
   */
  public function getCollaborationPermissionArray(AccountInterface $user, string $filterFormat): array {
    $filterFormatPermission = $this->filterFormatPermission($filterFormat);
    $collaborationPermissions = parent::getCollaborationPermissionArray($user, $filterFormat);

    $hasWriteAccess = $user->hasPermission($filterFormatPermission . NonRealtimeCollaborationPermissions::DOCUMENT_SUGGESTIONS);
    $hasWriteAccessGranted = in_array('document:write', $collaborationPermissions);
    if ($hasWriteAccess && !$hasWriteAccessGranted) {
      $collaborationPermissions[] = 'document:write';
    }

    return $collaborationPermissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCollaborationAccess(AccountInterface $user, string $filterFormat): array {
    $filterFormatPermission = $this->filterFormatPermission($filterFormat);

    $userAccess = parent::getUserCollaborationAccess($user, $filterFormat);
    $userAccess['document_suggestion'] = $user->hasPermission($filterFormatPermission . NonRealtimeCollaborationPermissions::DOCUMENT_SUGGESTIONS);

    return $userAccess;
  }

}
