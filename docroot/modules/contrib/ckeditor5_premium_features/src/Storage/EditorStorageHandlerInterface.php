<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Storage;

/**
 * Defines the interface for the handlers of the editor storage.
 */
interface EditorStorageHandlerInterface {

  /**
   * Checks if the editor of the given form element is CKEditor5.
   *
   * @param array $element
   *   The form element with the editor format defined.
   *
   * @return bool
   *   True if it is using CKEditor5, false otherwise.
   */
  public function isCkeditor5(array $element): bool;

  /**
   * Checks if any collaboration feature is enabled.
   *
   * @param array $element
   *   The form element with the editor format defined.
   *
   * @return bool
   *   True if it any of the plugin is collaboration feature, false otherwise.
   */
  public function hasCollaborationFeaturesEnabled(array $element): bool;

  /**
   * Returns an array of editors names with default states of track changes.
   *
   * @param array $element
   *   The form element with the editor format defined.
   * @param bool $rtc
   *   Is RTC module.
   *
   * @return array
   *   Array of track changes states.
   */
  public function getTrackChangesStates(array $element, bool $rtc = FALSE): array;

  /**
   * Checks if Document Outline feature is enabled in any of available text formats.
   *
   * @param array $element
   *   The form element with the editor format defined.
   *
   * @return bool
   *   True if Document Outline is enabled, false otherwise.
   */
  public function hasDocumentOutlineFeaturesEnabled(array $element): bool;

}
