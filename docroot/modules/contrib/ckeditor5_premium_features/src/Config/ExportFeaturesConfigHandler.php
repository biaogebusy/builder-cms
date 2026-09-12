<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Provides handler for the export features settings configuration.
 */
class ExportFeaturesConfigHandler implements ExportFeaturesConfigHandlerInterface {

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|null
   */
  protected ?ImmutableConfig $config = NULL;

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
    $this->generalConfig = $this->configFactory->get('ckeditor5_premium_features.settings');
  }

  /**
   * Sets the config based on the given name.
   *
   * @param string $name
   *   The config name.
   */
  public function setConfig(string $name): static {
    $this->config = $this->configFactory->get($name);

    return $this;
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
  public function getEnvironmentId(): ?string {
    return $this->config->get('env') ? $this->config->get('env') : $this->generalConfig->get('env');
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
  public function getConverterOptions(): array {
    $options = $this->config->get('converter_options') ?? [];

    return array_filter($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl(): string {
    return '';
  }

}
