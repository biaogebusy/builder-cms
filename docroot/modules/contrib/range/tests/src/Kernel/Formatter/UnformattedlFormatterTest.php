<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\range\Plugin\Field\FieldFormatter\RangeUnformattedFormatter;

/**
 * Tests the unformatted formatter.
 *
 * @group range
 */
class UnformattedlFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_float';
    $this->displayType = 'range_unformatted';
    $this->defaultSettings = RangeUnformattedFormatter::defaultSettings();

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
        '1234.5678', '7856.4321',
      ],
      [
        [],
        -10, 400,
        '-10', '400',
      ],
      // Test combined values.
      [
        [],
        1234.5678, 1234.5678,
        '1234.5678', '1234.5678',
      ],
    ];
  }

}
