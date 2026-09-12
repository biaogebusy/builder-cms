<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Utility;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Utility for AI permissions and active user context.
 */
class PermissionHelper {

  /**
   * Construct the helper with the current user context.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Returns the active user account proxy.
   */
  public function getCurrentUser(): AccountProxyInterface {
    return $this->currentUser;
  }

  /**
   * Convenience check for a given permission on the active user.
   */
  public function hasPermission(string $permission): bool {
    return $this->currentUser->hasPermission($permission);
  }

  /**
   * Get the CKEditor AI permissions for the current user.
   *
   * @param string $textFormatId
   *
   * @return array
   */
  public function getCKEditorAIPermissions(string $textFormatId = ''): array {

    $aiPermissions = $this->getStaticCKEditorAIPermissions();
    $this->moduleHandler->alter('ckeditor5_premium_features_ai_permissions', $aiPermissions, $this->currentUser, $textFormatId);

    // Return array with keys reset.
    return array_values($aiPermissions);
  }

  /**
   * Converts Drupal static permissions to the format expected by CKEditor AI.
   *
   * @return array|string[]
   */
  private function getStaticCKEditorAIPermissions(): array {
    if ($this->currentUser->hasPermission('ckeditor ai full access')) {
      return ['ai:admin'];
    }

    $result = [];
    if ($this->currentUser->hasPermission('ckeditor ai all models')) {
      $result[] = 'ai:models:*';
    }
    if ($this->currentUser->hasPermission('ckeditor ai conversations')) {
      $result[] = 'ai:conversations:read';
      $result[] = 'ai:conversations:write';
      if (!in_array('ai:models:*', $result)) {
        $result[] = 'ai:models:agent';
      }
    }
    if ($this->currentUser->hasPermission('ckeditor ai conversations websearch')) {
      $result[] = 'ai:conversations:websearch';
    }
    if ($this->currentUser->hasPermission('ckeditor ai reasoning')) {
      $result[] = 'ai:conversations:reasoning';
    }
    if ($this->currentUser->hasPermission('ckeditor ai context files')) {
      $result[] = 'ai:conversations:context:files:*';
    }
    if ($this->currentUser->hasPermission('ckeditor ai context urls')) {
      $result[] = 'ai:conversations:context:urls';
    }
    if ($this->currentUser->hasPermission('ckeditor ai actions')) {
      $result[] = 'ai:actions:*';
    }
    if ($this->currentUser->hasPermission('ckeditor ai reviews')) {
      $result[] = 'ai:reviews:*';
    }

    return $result;
  }

}
