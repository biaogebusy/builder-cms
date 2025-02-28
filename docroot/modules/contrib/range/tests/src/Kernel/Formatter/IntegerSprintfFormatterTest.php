<?php

namespace Drupal\Tests\range\Kernel\Formatter;

use Drupal\range\Plugin\Field\FieldFormatter\RangeIntegerSprintfFormatter;

/**
 * Tests the integer formatter.
 *
 * @group range
 */
class IntegerSprintfFormatterTest extends FormatterTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_integer';
    $this->displayType = 'range_integer_sprintf';
    $this->defaultSettings = RangeIntegerSprintfFormatter::defaultSettings();

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
        ['format_string' => '%05d'],
        1234, 5678,
        '01234', '05678',
      ],
      // Test combined values.
      [
        [],
        1234, 1234,
        '1234', '1234',
      ],
      [
        ['format_string' => '%06d'],
        1234, 1234,
        '001234', '001234',
      ],
    ];
  }

}
