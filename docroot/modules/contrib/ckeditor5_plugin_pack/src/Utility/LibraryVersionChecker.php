<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack\Utility;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the library version checker for ckeditor5.
 */
class LibraryVersionChecker {

  /**
   * Current version of core ckeditor5.
   *
   * @var string
   */
  protected string $ckeditor5Version;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected CacheBackendInterface $cacheBackend,
  ) {
    if ($this->moduleHandler->moduleExists('ckeditor5_premium_features_version_override')) {
      $versionOverride = $this->configFactory->get('ckeditor5_premium_features_version_override.settings');
      $enabled = $versionOverride->get('enabled');
      $version = $versionOverride->get('version');
      if ($enabled && $version) {
        $this->ckeditor5Version = $version;
        return;
      }
    }

    $cid = 'ckeditor5_plugin_pack_ckeditor5_version';
    $cachedVersion = $this->cacheBackend->get($cid);
    if ($cachedVersion) {
      $this->ckeditor5Version = $cachedVersion->data;
      return;
    }

    $filePath = DRUPAL_ROOT . '/core/core.libraries.yml';
    $fileContents = file_get_contents($filePath);
    $ymlData = Yaml::parse($fileContents);
    $this->cacheBackend->set($cid, $ymlData['ckeditor5']['version']);
    $this->ckeditor5Version = $ymlData['ckeditor5']['version'];
  }

  /**
   * Performs version compare.
   *
   * @param string $expectedVersion
   *   Expected or higher version of the library.
   *
   * @return bool
   *   If version is the same or higher returns TRUE.
   */
  public function isLibraryVersionHigherOrEqual(string $expectedVersion): bool {
    return version_compare($this->ckeditor5Version, $expectedVersion, '>=');
  }

  /**
   * Get CKEditor 5 version installed in the system.
   *
   * @return string
   *    CKEditor 5 version.
   */
  public function getCurrentVersion(): string {
    return $this->ckeditor5Version;
  }

}
