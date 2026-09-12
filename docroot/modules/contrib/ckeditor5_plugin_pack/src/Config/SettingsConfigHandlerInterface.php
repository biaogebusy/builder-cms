<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_plugin_pack\Config;

interface SettingsConfigHandlerInterface {

  const DLL_PATH_VERSION_TOKEN = 'VERSION_TOKEN';

  /**
   * Returns local DLL locations.
   *
   * @return string[]
   *   The potential local DLL locations.
   */
  public function getDllLocations(): array;

  /**
   * Gets the remote DLL location.
   *
   * @return string
   *   The URL of the DLL location.
   */
  public function getRemoteDllLocation(): string;

  /**
   * Gets the DLLs version.
   *
   * @return string
   *   The DLLs version.
   */
  public function getDllVersion(): string;

  /**
   * Indicates if local path to plugins is configured.
   * This means that we have to include local libraries instead of CDNs.
   *
   * @return bool
   */
  public function isLocalLibraryPathSpecified(): bool;

  /**
   * Indicates if CDN usage is blocked.
   *
   * @return bool
   */
  public function isCdnBlocked(): bool;

}
