<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\ckeditor5_premium_features\Plugin\CKEditor5Plugin\ExportBase;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Theme\ThemeManager;
use Drupal\Core\File\FileExists;
use Drupal\editor\EditorInterface;

/**
 * Css style list provider.
 */
class CssStyleProvider {

  /**
   * Creates CssStyleProvider instance.
   *
   * @param \Drupal\Core\Theme\ThemeManager $themeManager
   *   Theme ThemeManager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   File Url generator service.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface $ckeditor5PluginManager
   *   CKEditor5 Plugin Manager service.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   Library discovery service.
   */
  public function __construct(protected ThemeManager $themeManager,
                              protected FileSystemInterface $fileSystem,
                              protected FileUrlGeneratorInterface $fileUrlGenerator,
                              protected CKEditor5PluginManagerInterface $ckeditor5PluginManager,
                              protected LibraryDiscoveryInterface $libraryDiscovery) {
  }

  /**
   * Check if url is font css.
   *
   * @param string $url
   *   Url to style file.
   *
   * @return bool
   *   Is font url.
   */
  private function isFontCssFile(string $url): bool {
    return str_ends_with($url, 'fonts.css') || str_starts_with($url, '//fonts');
  }

  /**
   * Get List of styles file urls.
   *
   * @param bool $fonts_list
   *   Return fonts files.
   *
   * @return array
   *   Css file style list.
   */
  public function getCssStylesheetsUrls(bool $fonts_list = FALSE): array {
    $styles_urls = $this->getCssFilesListFromActiveTheme();
    $fonts = [];
    foreach ($styles_urls as $styles_url) {
      if ($this->isFontCssFile($styles_url)) {
        $fonts[] = $styles_url;
      }
    }

    return $fonts_list ? $fonts : array_diff($styles_urls, $fonts);
  }

  /**
   * Get list of css styles file used in current theme.
   *
   * @return array
   *   List of urls.
   */
  public function getCssFilesListFromActiveTheme(): array {
    $active_theme = $this->themeManager->getActiveTheme();

    return _ckeditor5_theme_css($active_theme->getName());
  }

  /**
   * Get list all css used in current editor instance.
   *
   * Formatted in pattern:
   *
   * @see isFontCssFile();
   * - EDITOR_STYLES (default one).
   * - Theme fonts files.
   * - Theme non font files.
   * - Editor attached files.
   */
  public function getFormattedListOfCssFiles(EditorInterface $editor): array {
    $fonts = $this->getCssStylesheetsUrls(TRUE);
    $non_fonts = $this->getCssStylesheetsUrls();
    $editor_styles = $this->getEditorAttachedStylesheets($editor);

    return array_merge(['EDITOR_STYLES'], $fonts, $non_fonts, $editor_styles);
  }

  /**
   * Update file with the custom css.
   *
   * Delete file if css content is NULL and the file exists.
   *
   * @param string|null $customCss
   *   CSS content.
   * @param string $fileName
   *   File name.
   * @param string $directoryPath
   *   Directory path where the file is located.
   */
  public function updateCustomCssFile(?string $customCss, string $fileName, string $directoryPath = ExportBase::CUSTOM_CSS_DIRECTORY_PATH): void {
    $filePath = $directoryPath . $fileName . '.css';
    if ($customCss) {
      $this->fileSystem->prepareDirectory($directoryPath, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->saveData($customCss, $filePath, FileExists::Replace);
    }
    else {
      $relativePath = $this->fileUrlGenerator->generateString($filePath);
      if ($this->fileSystem->getDestinationFilename($relativePath, FileExists::Error)) {
        $this->fileSystem->delete($filePath);
      }
    }
  }

  /**
   * Get the custom css file path.
   *
   * @param string $fileName
   *   File name.
   * @param string $directoryPath
   *   Directory path where the file is located.
   *
   * @return bool|string
   *   Relative path to the file or FALSE.
   */
  public function getCustomCssFile(string $fileName, string $directoryPath = ExportBase::CUSTOM_CSS_DIRECTORY_PATH):bool|string {
    $filePath = $directoryPath . $fileName . '.css';
    $relativePath = $this->fileUrlGenerator->generateString($filePath);
    if (!$this->fileSystem->getDestinationFilename($filePath, FileExists::Error)) {
      return $relativePath;
    }
    return FALSE;
  }

  /**
   * Gets all stylesheets attached to a CKEditor 5 instance.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor entity.
   *
   * @return array
   *   An array of stylesheet urls.
   */
  private function getEditorAttachedStylesheets(EditorInterface $editor): array {
    $stylesheets = [];

    // Only process CKEditor 5 editors.
    if ($editor->getEditor() !== 'ckeditor5') {
      return $stylesheets;
    }

    // Get the base library.
    $libraries = ['system/base'];
    // Get all libraries attached to this editor using the plugin manager.
    $libraries = array_merge($libraries, $this->ckeditor5PluginManager->getEnabledLibraries($editor));

    // Process each library to extract CSS files.
    foreach ($libraries as $library) {
      list($extension, $name) = explode('/', $library, 2);
      $library_definition = $this->libraryDiscovery->getLibraryByName($extension, $name);

      if (!$library_definition) {
        continue;
      }

      // Extract CSS files from the library.
      if (isset($library_definition['css'])) {
        foreach ($library_definition['css'] as $css_item) {
          if (isset($css_item['data']) && $css_item['type'] === 'file') {
            $stylesheets[] = $css_item['data'];
          }
        }
      }
    }

    // Convert relative paths to full URLs.
    foreach ($stylesheets as $key => $path) {
      $stylesheets[$key] = $this->fileUrlGenerator->generateString($path);
    }

    return $stylesheets;
  }

}
