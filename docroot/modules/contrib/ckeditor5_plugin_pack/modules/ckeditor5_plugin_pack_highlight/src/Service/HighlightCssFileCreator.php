<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_highlight\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Service for creating CSS files for the Highlight plugin.
 */
class HighlightCssFileCreator {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Default CSS for highlight markers.
   */
  const DEFAULT_CSS = '
.marker-yellow { background-color: #fdfd77; }
.marker-green { background-color: #62f962; }
.marker-pink { background-color: #fc7899; }
.marker-blue { background-color: #72ccfd; }
.pen-red { background-color: transparent; color: #e71313; }
.pen-green { background-color: transparent; color: #128a00; }
  ';

  /**
   * Constructs a new HighlightCssFileCreator object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * Saves CSS file for the Highlight plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $format
   *   The text format ID.
   * @param array|null $form
   *   The form array, if available.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state, if available.
   *
   * @return bool
   *   TRUE if the file was saved successfully, FALSE otherwise.
   */
  public function saveHighlightCss(array $configuration, string $format, ?array $form = NULL, ?FormStateInterface $form_state = NULL): bool {
    $css = '';
    if ($configuration['use_default_markers'] ?? TRUE) {
      $css .= self::DEFAULT_CSS;
    }

    if ($form && $form_state) {
      $css .= $this->buildCustomMarkersCSS($configuration, $format, $form, $form_state);
    } elseif (!empty($configuration['options'])) {
      $css .= $this->buildCustomMarkersCssFromConfig($configuration, $format);
    }

    $directory = 'public://ckeditor5/';
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      return FALSE;
    }

    $filename = 'ckeditor5_plugin_pack_highlight-' . $format . '.css';
    $filePath = $directory . $filename;

    $this->fileSystem->saveData($css, $filePath, FileExists::Replace);

    return TRUE;
  }

  /**
   * Build CSS for the custom markers from form input.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $format
   *   The text format ID.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The CSS content.
   */
  public function buildCustomMarkersCSS(array $configuration, string $format, array $form, FormStateInterface $form_state): string {
    $userInput = $form_state->getUserInput();
    if (!isset($userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'])) {
      return '';
    }
    $input = $userInput['editor']['settings']['plugins']['ckeditor5_plugin_pack_highlight__highlight']['custom_marker_wrapper'];
    if (empty($input)) {
      return '';
    }

    $data = '';
    $format = $form['format']['#value'] ?? $form_state->getCompleteForm()['format']['#value'] ?? $format;

    foreach ($input as $marker) {
      $type = array_filter($marker['type'] ?? [], fn($x) => !empty($x));
      if (empty($type) || empty($marker['title'])) {
        continue;
      }

      foreach ($type as $typeValue) {
        if (!$typeValue) {
          continue;
        }

        $className = $this->getHighlightClass($typeValue, $format, $marker['title'], $marker['class_suffix'] ?? NULL);

        if ($typeValue === 'marker') {
          $className .= ' { ' . 'background-color: ' . $marker['color'] . '; }';
        }
        else {
          $className .= ' { ' . 'background-color: transparent; color: ' . $marker['color'] . '; }';
        }

        $data .= '.' . $className . "\n";
      }
    }

    return $data;
  }

  /**
   * Build CSS for the custom markers from configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $format
   *   The text format ID.
   *
   * @return string
   *   The CSS content.
   */
  private function buildCustomMarkersCssFromConfig(array $configuration, string $format): string {
    $data = '';
    $markers = $configuration['options'] ?? [];

    foreach ($markers as $marker) {
      if (empty($marker['title']) || empty($marker['type'])) {
        continue;
      }

      $typeValues = is_array($marker['type'])
        ? array_filter($marker['type'], fn($value) => !empty($value))
        : [$marker['type']];

      foreach ($typeValues as $typeKey => $typeValue) {
        $typeValue = is_string($typeKey) ? $typeKey : $typeValue;
        $className = $this->getHighlightClass($typeValue, $format, $marker['title'], $marker['class_suffix'] ?? NULL);

        if ($typeValue === 'marker') {
          $className .= ' { ' . 'background-color: ' . $marker['color'] . '; }';
        }
        else {
          $className .= ' { ' . 'background-color: transparent; color: ' . $marker['color'] . '; }';
        }

        $data .= '.' . $className . "\n";
      }
    }

    return $data;
  }

  /**
   * Returns CSS class for the marker.
   *
   * @param string $type
   *   The marker type.
   * @param string $textFormat
   *   The text format ID.
   * @param string $markerTitle
   *   The marker title.
   * @param string|null $suffix
   *   The class suffix, if any.
   *
   * @return string
   *   The CSS class.
   */
  public function getHighlightClass(string $type, string $textFormat, string $markerTitle, ?string $suffix): string {
    if ($suffix) {
      $class = 'custom-highlight' . '-' . $type . '-' . $suffix;
    }
    else {
      $class = 'custom-highlight' . '-' . $type . '-' . str_replace(' ', '-', trim($markerTitle)) . '-' . $textFormat;
    }
    return $class;
  }

}
