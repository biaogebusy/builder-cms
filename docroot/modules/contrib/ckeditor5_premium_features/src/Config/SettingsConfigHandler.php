<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Config;

use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;

/**
 * Provides the utility service for handling the stored settings configuration.
 */
class SettingsConfigHandler implements SettingsConfigHandlerInterface {

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   *  The CKEditor 5 library version checker.
   *
   * @var \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker
   */
  protected $libraryVersionChecker;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the handler.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker $library_version_checker
   *   The library version checker.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   Library discovery service.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory,
                              protected LibraryDiscoveryInterface $library_discovery,
                              protected LibraryVersionChecker $library_version_checker,
                              protected ModuleHandlerInterface $module_handler) {
    $this->config = $this->configFactory->get('ckeditor5_premium_features.settings');
    $this->libraryDiscovery = $library_discovery;
    $this->libraryVersionChecker = $library_version_checker;
    $this->moduleHandler = $this->module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getLicenseKey(): ?string {
    return $this->config->get('license_key');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessKey(): ?string {
    return $this->config->get('access_key');
  }

  /**
   * {@inheritdoc}
   */
  public function getWebSocketUrl(): ?string {
    $base_path = $this->config->get('web_socket_url') ?: $this->getDefaultWebSocketUrl();

    $base_path = trim($base_path, ' /');

    return $this->replaceTokens($base_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironmentId(): ?string {
    return $this->config->get('env');
  }

  /**
   * {@inheritdoc}
   */
  public function getDevelopmentTokenUrl(): ?string {
    return $this->config->get('dev_token_url');
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUrl($filterFormatId = NULL): string {
    $type = $this->config->get('auth_type');

    if ($type === 'dev_token' && $token_url = $this->getDevelopmentTokenUrl()) {
      return $token_url;
    }

    $options = [];

    if ($filterFormatId) {
      $options['query'] = ['format' => $filterFormatId];
    }

    if ($type === 'key' && $this->getAccessKey() && $this->getEnvironmentId()) {
      return Url::fromRoute('ckeditor5_premium_features.endpoint.jwt_token', [], $options)
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    // The empty string allows to use the evaluation version note.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDllLocation(string $file_name = ''): string {
    $base_path = $this->config->get('dll_location') ?: $this->getDefaultDllLocation();

    $base_path = rtrim($base_path, ' /') . '/';

    $base_path = $this->replaceTokens($base_path);

    return $base_path . $file_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiUrl(): string {
    $base_path = $this->config->get('api_url') ?: $this->getDefaultApiUrl();

    $base_path = trim($base_path, ' /') . '/';

    return $this->replaceTokens($base_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getApiKey(): ?string {
    return $this->config->get('api_key');
  }

  /**
   * {@inheritdoc}
   */
  public function isApiKeyRequired(): bool {
    return $this->moduleHandler->moduleExists('ckeditor5_premium_features_realtime_collaboration');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultDllLocation(): string {
    return 'https://cdn.ckeditor.com/ckeditor5/' . SettingsConfigHandlerInterface::DLL_PATH_VERSION_TOKEN . '/dll/';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultWebSocketUrl(): string {
    return 'wss://' . SettingsConfigHandlerInterface::ORGANIZATION_ID_TOKEN . '.cke-cs.com/ws';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultApiUrl(): string {
    return 'https://' . SettingsConfigHandlerInterface::ORGANIZATION_ID_TOKEN . '.cke-cs.com/api/v5/'
      . SettingsConfigHandlerInterface::ENVIRONMENT_ID_TOKEN . '/';
  }

  /**
   * Gets the DLLs version.
   *
   * @return string
   *   The DLLs version.
   */
  public function getDllVersion(): string {
    $library = $this->libraryDiscovery->getLibraryByName('core', 'ckeditor5');

    return $library['version'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganizationId(): ?string {
    return $this->config->get('organization_id');
  }

  /**
   * {@inheritDoc}
   */
  public function isAlterNodeFormCssEnabled(): bool {
    return (bool) $this->config->get('alter_node_form_css');
  }

  /**
   * {@inheritdoc}
   */
  public function isAddKeyToAllInstancesEnabled(): bool {
    if (!$this->libraryVersionChecker->isLibraryVersionHigherOrEqual('44.0.0')) {
      // Ignore the setting for versions older than 44.0.0 the license can be always added as there is no usage based billing.
      return TRUE;
    }

    return (bool) $this->config->get('add_key_to_all_instances');
  }

  /**
   * Replaces supported tokens in passed parameter path..
   *
   * @param string $path
   *   A URL with potential tokens to replace.
   */
  protected function replaceTokens(string $path): string {
    $tokens = [
      SettingsConfigHandlerInterface::ENVIRONMENT_ID_TOKEN => $this->getEnvironmentId(),
      SettingsConfigHandlerInterface::ORGANIZATION_ID_TOKEN => $this->getOrganizationId(),
      SettingsConfigHandlerInterface::DLL_PATH_VERSION_TOKEN => $this->getDllVersion(),
    ];

    foreach ($tokens as $token => $value) {
      if (!$value) {
        continue;
      }
      $path = str_replace($token, $value, $path);
    }

    return $path;
  }

}
