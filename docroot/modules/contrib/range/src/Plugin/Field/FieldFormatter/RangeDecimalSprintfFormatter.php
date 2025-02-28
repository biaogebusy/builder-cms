<?php

namespace Drupal\range\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the 'range_decimal_sprintf' formatter.
 *
 * The 'Formatted string' formatter is different for integer fields, and for
 * decimal and float fields in order to be able to use different settings.
 *
 * @FieldFormatter(
 *   id = "range_decimal_sprintf",
 *   label = @Translation("Formatted string"),
 *   field_types = {
 *     "range_decimal",
 *     "range_float"
 *   },
 *   weight = 5
 * )
 */
class RangeDecimalSprintfFormatter extends RangeIntegerSprintfFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'format_string' => '%.2f',
    ] + parent::defaultSettings();
  }

}
