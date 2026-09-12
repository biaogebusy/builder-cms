<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Generator;

use Drupal\ckeditor5_premium_features\CollaborationAccessHandlerInterface;
use Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface;
use Drupal\ckeditor5_premium_features\Utility\UserHelper;
use Drupal\Core\Session\AccountProxyInterface;
use Firebase\JWT\JWT;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the JWT Token generator service.
 */
class TokenGenerator implements TokenGeneratorInterface {

  public const ALGORITHM = 'HS512';

  /**
   * Constructs the token generator instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\ckeditor5_premium_features\Config\SettingsConfigHandlerInterface $settingsConfigHandler
   *   The settings config handler.
   * @param \Drupal\ckeditor5_premium_features\Utility\UserHelper $userHelper
   *   Helper for getting user data.
   * @param CollaborationAccessHandlerInterface $accessHandler
   *   The Collaboration Access Handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\DependencyInjection\Container $serviceContainer
   *   The service container.
   *
   * @note The account will be used later in collaboration features.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected SettingsConfigHandlerInterface $settingsConfigHandler,
    protected UserHelper $userHelper,
    protected CollaborationAccessHandlerInterface $accessHandler,
    protected ModuleHandlerInterface $moduleHandler,
    protected Container $serviceContainer
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function generate($filterFormatId = NULL): string {
    $access = [];

    $isRtcPermissionsEnabled = FALSE;
    if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_realtime_collaboration')) {
      $rtcConfig = $this->serviceContainer->get('ckeditor5_premium_features_realtime_collaboration.collaboration_settings');
      $isRtcPermissionsEnabled = $rtcConfig->isPermissionsEnabled();
    }

    if ($filterFormatId && $isRtcPermissionsEnabled) {
      $access['permissions'] = $this->accessHandler->getCollaborationPermissionArray($this->account, $filterFormatId);
    }
    else {
      $access['role'] = 'writer';
    }

    $payload = [
      'aud' => $this->settingsConfigHandler->getEnvironmentId(),
      'iat' => time(),
      'sub' => $this->userHelper->getUserUuid($this->account) ?? $this->userHelper->generateSiteUserId($this->account),
      'auth' => [
        'collaboration' => [
          '*' => $access,
        ],
      ],
    ];

    if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_ai')) {
      $permissionHelper = \Drupal::service('ckeditor5_premium_features_ai.permission_helper');
      $array = $permissionHelper->getCKEditorAIPermissions($filterFormatId);
      $payload['auth']['ai']['permissions'] = $array;
    }

    $userData = $this->userHelper->getUserData($this->account);
    $payload['user'] = $userData;

    return JWT::encode($payload, $this->settingsConfigHandler->getAccessKey(), static::ALGORITHM);
  }

}
