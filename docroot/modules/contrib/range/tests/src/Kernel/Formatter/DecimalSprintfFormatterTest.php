<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\range\Plugin\Field\FieldFormatter\RangeDecimalSprintfFormatter;

/**
 * Tests the decimal formatter.
 *
 * @group range
 */
class DecimalSprintfFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_float';
    $this->displayType = 'range_decimal_sprintf';
    $this->defaultSettings = RangeDecimalSprintfFormatter::defaultSettings();

    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldFormatterDataProvider() {
    return [
      // Test separate values.
      [
        [],
        1234.5678, 7856.4321,
        '1234.57', '7856.43',
      ],
      [
        [
          'format_string' => '%d',
        ],
        1234.5678, 7856.4321,
        '1234', '7856',
      ],
      // Test combined values.
      [
        [],
        1234.5678, 1234.5678,
        '1234.57', '1234.57',
      ],
      [
        [
          'format_string' => '%.3e',
        ],
        1234.5678, 1234.5678,
        '1.235e+3', '1.235e+3',
      ],
    ];
  }

}
