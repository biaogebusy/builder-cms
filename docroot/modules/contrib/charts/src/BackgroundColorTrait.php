<?php

namespace Drupal\charts;

use Drupal\Component\Utility\Xss;

/**
 * Contains a method to add a background color when not supported by the API.
 */
trait BackgroundColorTrait {

  /**
   * Apply a background color to the Chart element.
   *
   * @param array $element
   *   The Chart element.
   *
   * @return array
   *   The Chart element with background color applied if provided.
   */
  public static function applyBackgroundColor(array $element): array {
    if (!empty($element['#background'])) {
      $element['#attributes']['style'][] = 'background-color:' . Xss::filter($element['#background']) . ';';
    }
    return $element;
  }

}
