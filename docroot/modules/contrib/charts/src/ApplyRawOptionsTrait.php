<?php

namespace Drupal\charts;

use Drupal\Component\Utility\NestedArray;

/**
 * Contains helper method to apply raw options within library classes.
 */
trait ApplyRawOptionsTrait {

  /**
   * Merge in chart raw options.
   *
   * @param array $element
   *   The element.
   * @param array $element_definition
   *   The definition to be updated with raw options.
   *
   * @return array
   *   The merged element definition.
   */
  public static function applyRawOptions(array $element, array $element_definition): array {
    if (!empty($element['#raw_options'])) {
      $element_definition = NestedArray::mergeDeepArray([
        $element_definition,
        $element['#raw_options'],
      ]);
    }
    return $element_definition;
  }

}
