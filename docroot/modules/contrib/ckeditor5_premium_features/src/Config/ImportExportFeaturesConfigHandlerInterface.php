<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Config;

/**
 * Defines the interface for handling import and export features settings configuration.
 */
interface ImportExportFeaturesConfigHandlerInterface {

  /**
   * Gets the converter URL if defined.
   *
   * @return string|null
   *   The URL defaults to null.
   */
  public function getConverterUrl(): ?string;

  /**
   * Checks if the converter URL was defined.
   *
   * @return bool
   *   True if URL was defined, false otherwise.
   */
  public function hasConverterUrl(): bool;

  /**
   * Gets the environment id if defined.
   *
   * @return string|null
   *   The environment id, defaults to null.
   */
  public function getEnvironmentId(): ?string;

  /**
   * Gets the Access key if defined.
   *
   * @return string|null
   *   The access key, defaults to null.
   */
  public function getAccessKey(): ?string;

  /**
   * Gets the token URL based on the configuration values.
   *
   * @return string
   *   The token URL.
   */
  public function getTokenUrl(): string;

}
