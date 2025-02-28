<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\range\Plugin\Field\FieldFormatter\RangeIntegerFormatter;

/**
 * Tests the integer formatter.
 *
 * @group range
 */
class IntegerFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_integer';
    $this->displayType = 'range_integer';
    $this->defaultSettings = RangeIntegerFormatter::defaultSettings();

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
        1234, 5678,
        '1234', '5678',
      ],
      [
        ['thousand_separator' => ' '],
        1234, 5678,
        '1 234', '5 678',
      ],
      // Test combined values.
      [
        [],
        1234, 1234,
        '1234', '1234',
      ],
      [
        ['thousand_separator' => ','],
        1234, 1234,
        '1,234', '1,234',
      ],
    ];
  }

}
