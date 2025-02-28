<?php

namespace Drupal\range\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field formatter settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d6_range_field_formatter_settings",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldFormatterSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Get the range field formatter settings.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$module, $format] = $value;
    $type = $row->getDestinationProperty('options/type');

    $map = [
      'range_decimal' => [
        'default' => [
          'field_prefix_suffix' => TRUE,
        ],
        'us_0' => [
          'scale' => 0,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
        'us_1' => [
          'scale' => 1,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
        'us_2' => [
          'scale' => 2,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
        'be_0' => [
          'scale' => 0,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
        'be_1' => [
          'scale' => 1,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
        'be_2' => [
          'scale' => 2,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
        'fr_0' => [
          'scale' => 0,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
        'fr_1' => [
          'scale' => 1,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
        'fr_2' => [
          'scale' => 2,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_integer' => [
        'default' => [
          'field_prefix_suffix' => TRUE,
        ],
        'us_0' => [
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
        'be_0' => [
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
        'fr_0' => [
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_unformatted' => [
        'unformatted' => [
          'field_prefix_suffix' => TRUE,
        ],
      ],
    ];

    return $map[$type][$format] ?? [];
  }

}
