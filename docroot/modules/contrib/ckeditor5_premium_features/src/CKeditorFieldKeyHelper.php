<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features;

use Drupal\ckeditor5_premium_features\Utility\Html;

/**
 * Class for generating unique field IDs.
 */
class CKeditorFieldKeyHelper {

  /**
   * Gets the element unique HTML ID.
   *
   * @param string $elementId
   *   Form element ID.
   *
   * @return string
   *   The ID.
   */
  public static function getElementUniqueId(string $elementId): string {
    $id = 'id-' . hash('crc32', static::cleanElementDrupalId($elementId));

    return Html::getId($id);
  }

  /**
   * Returns cleaned form element ID (without "--POSTFIX").
   *
   * @param string $elementId
   *   Form element ID.
   */
  public static function cleanElementDrupalId(string $elementId): string {
    $elementId = str_replace('_', '-', $elementId);
    $elementParts = explode('--', $elementId);

    return reset($elementParts);
  }

}
