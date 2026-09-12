<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_version_override\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Class OverrideHandler.
 */
class OverrideHandler {

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * OverrideHandler constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(protected ModuleExtensionList $moduleExtensionList,
                              ConfigFactoryInterface $config_factory
    ) {
    $this->config = $config_factory->get('ckeditor5_premium_features_version_override.settings');
  }

  /**
   * Get the libraries location.
   *
   * @return string
   */
  public function getAbsoluteLibrariesLocation(): string {
    return DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('ckeditor5_premium_features_version_override') . '/assets/ckeditor5/';
  }

  private function getLibrariesLocation(): string {
    return $this->moduleExtensionList->getPath('ckeditor5_premium_features_version_override') . '/assets/ckeditor5/';
  }

  /**
   * Handle the override.
   *
   * @param $libraries
   */
  public function handleOverride(&$libraries): void {
    $librariesMapping = $this->getLibrariesMapping();
    $absoluteLibrariesLocation = $this->getAbsoluteLibrariesLocation();
    $version = $this->getVersion();
    if (!$version) {
      return;
    }
    foreach ($librariesMapping as $key => $library) {
      $absoluteFileLocation = $absoluteLibrariesLocation . $version . '/' . $library . '/' . $library . '.js';
      if (file_exists($absoluteFileLocation)) {
        $fileLocation = '/' . $this->getLibrariesLocation() . $version . '/' . $library . '/' . $library . '.js';
      }
      else {
        $fileLocation = 'https://cdn.ckeditor.com/ckeditor5/' . $version . '/dll/' .  $library . '/' . $library . '.js';
      }

      $libraries[$key]['js'] = [
        $fileLocation => [
          'preprocess' => FALSE,
          'minified' => TRUE,
        ],
      ];
      $libraries[$key]['version'] = $version;
      $libraries[$key]['remote'] = 'https://github.com/ckeditor/ckeditor5';
      $libraries[$key]['license']['url'] = 'https://raw.githubusercontent.com/ckeditor/ckeditor5/v' . $version . '/LICENSE.md';
    }
  }

  /**
   * Get the library version.
   *
   * @return string|null
   */
  private function getVersion(): ?string {
    return $this->config->get('version');
  }

  /**
   * Check if the override is enabled.
   *
   * @return bool
   */
  public function isOverrideEnabled(): bool {
    $isEnabled = $this->config->get('enabled');
    $version = $this->config->get('version');

    return $isEnabled && $version && $version !== 'none';
  }

  /**
   * Get the libraries mapping.
   *
   * @return array
   */
  private function getLibrariesMapping(): array {
    return [
      'ckeditor5' => 'ckeditor5-dll',
      'ckeditor5.editorDecoupled' => 'editor-decoupled',
      'ckeditor5.removeFormat' => 'remove-format',
      'ckeditor5.essentials' => 'essentials',
      'ckeditor5.language' => 'language',
      'ckeditor5.image' => 'image',
      'ckeditor5.htmlSupport' => 'html-support',
      'ckeditor5.horizontalLine' => 'horizontal-line',
      'ckeditor5.codeBlock' => 'code-block',
      'ckeditor5.editorClassic' => 'editor-classic',
      'ckeditor5.specialCharacters' => 'special-characters',
      'ckeditor5.indent' => 'indent',
      'ckeditor5.blockquote' => 'block-quote',
      'ckeditor5.list' => 'list',
      'ckeditor5.heading' => 'heading',
      'ckeditor5.link' => 'link',
      'ckeditor5.alignment' => 'alignment',
      'ckeditor5.pasteFromOffice' => 'paste-from-office',
      'ckeditor5.basic' => 'basic-styles',
      'ckeditor5.table' => 'table',
      'ckeditor5.sourceEditing' => 'source-editing',
      'ckeditor5.style' => 'style',
      'ckeditor5.showBlocks' => 'show-blocks',
      'ckeditor5.autoformat' => 'autoformat',
    ];
  }

}
