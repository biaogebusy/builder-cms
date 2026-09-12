<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Handles AI module configuration access.
 */
class AiConfigHandler {

  public const DEFAULT_SERVICE_URL = 'https://ai.cke-cs.com/v1';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Returns the configured AI service URL or default if empty.
   */
  public function getServiceUrl(): string {
    $config = $this->configFactory->get('ckeditor5_premium_features_ai.settings');
    $value = trim((string) $config->get('service_url'));
    return !empty($value) ? $value : self::DEFAULT_SERVICE_URL;
  }
}
