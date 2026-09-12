<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Config;

/**
 * Defines the settings config interface.
 */
interface SettingsConfigHandlerInterface {

  const DLL_PATH_VERSION_TOKEN = 'VERSION_TOKEN';
  const ORGANIZATION_ID_TOKEN = 'ORGANIZATION_ID';
  const ENVIRONMENT_ID_TOKEN = 'ENVIRONMENT_ID';

  /**
   * Getter for the license key.
   *
   * @return string|null
   *   The license key if defined, null otherwise.
   */
  public function getLicenseKey(): ?string;

  /**
   * Getter for the access key.
   *
   * @return string|null
   *   The access key if defined, null otherwise.
   */
  public function getAccessKey(): ?string;

  /**
   * Getter for the web socket url.
   *
   * @return string|null
   *   The web socket url if defined, null otherwise.
   */
  public function getWebSocketUrl(): ?string;

  /**
   * Getter for the env id.
   *
   * @return string|null
   *   The id if defined, null otherwise.
   */
  public function getEnvironmentId(): ?string;

  /**
   * Getter for the development token url.
   *
   * @return string|null
   *   The development token url if defined, null otherwise.
   */
  public function getDevelopmentTokenUrl(): ?string;

  /**
   * Gets the token URL based on the configuration values.
   *
   * @param string|null $filterFormatId
   *   The filter format id.
   *
   * @return string
   *   The token URL.
   */
  public function getTokenUrl(?string $filterFormatId): string;

  /**
   * Gets the DLLs location.
   *
   * @return string
   *   The DLLs location.
   */
  public function getDllLocation(string $file_name = ''): string;

  /**
   * Gets the API base path.
   */
  public function getApiUrl(): string;

  /**
   * Gets the API authorisation Key.
   */
  public function getApiKey(): ?string;

  /**
   * Check if Api key is required i.e. Realtime collaboration module is installed.
   *
   * @return bool
   */
  public function isApiKeyRequired(): bool;

  /**
   * Gets the default DLL location if it was not overridden in the config.
   *
   * @return string
   *   The URL of the DLL location.
   */
  public function getDefaultDllLocation(): string;

  /**
   * Gets the default Web Socket URL.
   */
  public function getDefaultWebSocketUrl(): string;

  /**
   * Gets the default Api URL.
   */
  public function getDefaultApiUrl(): string;

  /**
   * Gets the DLLs version.
   *
   * @return string
   *   The DLLs version.
   */
  public function getDllVersion(): string;

  /**
   * Gets the organization ID based on the configuration values.
   *
   * @return string|null
   *   The URL of the DLL location.
   */
  public function getOrganizationId(): ?string;

  /**
   * Get alter_node_form_css config field.
   *
   * @return bool
   *   True if config field is set to true.
   */
  public function isAlterNodeFormCssEnabled(): bool;

  /**
   * Checks if license key should be added to all CKEditor 5 instances.
   *
   * @return bool
   */
  public function isAddKeyToAllInstancesEnabled(): bool;

}
