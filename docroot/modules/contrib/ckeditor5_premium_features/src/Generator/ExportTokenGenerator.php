<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Generator;

use Drupal\ckeditor5_premium_features\Config\ImportExportFeaturesConfigHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Firebase\JWT\JWT;

/**
 * Provides the JWT Token generator service.
 */

class ExportTokenGenerator implements TokenGeneratorInterface {

  public const ALGORITHM = 'HS512';

  /**
   * Constructs the token generator instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\ckeditor5_premium_features\Config\ExportFeaturesConfigHandlerInterface $settingsConfigHandler
   *   The settings config handler.
   *
   * @note The account will be used later in collaboration features.
   */
  public function __construct(
    protected AccountProxyInterface $account,
    protected ImportExportFeaturesConfigHandlerInterface $settingsConfigHandler,
  ) {
  }

  /**
   * Generates the JWT token.
   *
   * @return string
   *   The token.
   */
  public function generate($filterFormatId = NULL): string {
    $payload = [
      'aud' => $this->settingsConfigHandler->getEnvironmentId(),
      'iat' => time(),
      'sub' => $this->account->id(),
      'user' => [
        'email' => $this->account->getEmail(),
        'name' => $this->account->getAccountName(),
      ],
    ];
    if (empty($payload['user']['email'])) {
      unset($payload['user']['email']);
    }

    return JWT::encode($payload, $this->settingsConfigHandler->getAccessKey(), static::ALGORITHM);
  }

}
