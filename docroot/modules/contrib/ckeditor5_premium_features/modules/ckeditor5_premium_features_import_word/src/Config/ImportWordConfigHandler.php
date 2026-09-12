<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Url;

/**
 * Helper for Import from Word configuration.
 */
class ImportWordConfigHandler implements ImportWordConfigHandlerInterface {

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The configuration object for the general Premium Features settings
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $generalConfig;

  /**
   * Constructs the handler.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {
    $this->config = $this->configFactory->get('ckeditor5_premium_features_import_word.settings');
    $this->generalConfig = $this->configFactory->get('ckeditor5_premium_features.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function isWordStylesEnabled(): bool {
    return (bool) $this->config->get('word_styles');
  }

  /**
   * {@inheritdoc}
   */
  public function getConverterUrl(): ?string {
    return $this->config->get('converter_url');
  }

  /**
   * {@inheritdoc}
   */
  public function hasConverterUrl(): bool {
    return (bool) $this->getConverterUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessKey(): ?string {
    return $this->config->get('access_key') ? $this->config->get('access_key') : $this->generalConfig->get('access_key');
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentId(): ?string {
    return $this->config->get('env') ? $this->config->get('env') : $this->generalConfig->get('env');
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl(): string {
    if ($this->getAccessKey() && $this->getEnvironmentId()) {
      return Url::fromRoute('ckeditor5_premium_features_import_word.endpoint.jwt_token')
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    return '';
  }

}
