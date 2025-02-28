<?php

namespace Drupal\range\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the 'range_unformatted' formatter.
 *
 * @FieldFormatter(
 *   id = "range_unformatted",
 *   label = @Translation("Unformatted"),
 *   field_types = {
 *     "range_integer",
 *     "range_decimal",
 *     "range_float"
 *   },
 *   weight = 10
 * )
 */
class RangeUnformattedFormatter extends RangeFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function formatNumber($number) {
    return $number;
  }

}
