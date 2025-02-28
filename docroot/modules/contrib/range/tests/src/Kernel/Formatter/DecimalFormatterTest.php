<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\range\Plugin\Field\FieldFormatter\RangeDecimalFormatter;

/**
 * Tests the decimal formatter.
 *
 * @group range
 */
class DecimalFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_float';
    $this->displayType = 'range_decimal';
    $this->defaultSettings = RangeDecimalFormatter::defaultSettings();

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
          'scale' => 3,
          'thousand_separator' => ' ',
          'decimal_separator' => ',',
        ],
        1234.5678, 7856.4321,
        '1 234,568', '7 856,432',
      ],
      // Test combined values.
      [
        [],
        1234.5678, 1234.5678,
        '1234.57', '1234.57',
      ],
      [
        [
          'scale' => 3,
          'thousand_separator' => ' ',
          'decimal_separator' => ',',
        ],
        1234.5678, 1234.5678,
        '1 234,568', '1 234,568',
      ],
    ];
  }

}
