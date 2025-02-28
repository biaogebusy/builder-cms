<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d6\RangeFieldInstanceDefaults;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d6\RangeFieldInstanceDefaults
 * @group range
 */
class RangeFieldInstanceDefaultsTest extends UnitTestCase {

  /**
   * Test the range field default value transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($widget_settings, $expected) {
    $plugin = new RangeFieldInstanceDefaults([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform([[], $widget_settings], $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'no default value' => [
        [],
        [],
      ],
      'null default value' => [
        [
          'default_value' => [['value' => NULL]],
        ],
        [],
      ],
      'empty string default value' => [
        [
          'default_value' => [['value' => '']],
        ],
        [],
      ],
      'zero integer default value' => [
        [
          'default_value' => [['value' => 0]],
        ],
        [
          [
            'from' => 0,
            'to' => 0,
          ],
        ],
      ],
      'zero float default value' => [
        [
          'default_value' => [['value' => 0.0]],
        ],
        [
          [
            'from' => 0.0,
            'to' => 0.0,
          ],
        ],
      ],
      'integer default value' => [
        [
          'default_value' => [['value' => 205]],
        ],
        [
          [
            'from' => 205,
            'to' => 205,
          ],
        ],
      ],
      'float default value' => [
        [
          'default_value' => [['value' => 15.50]],
        ],
        [
          [
            'from' => 15.50,
            'to' => 15.50,
          ],
        ],
      ],
      'extra settings present' => [
        [
          'default_value' => [['value' => -777]],
          'default_value_php' => NULL,
        ],
        [
          [
            'from' => -777,
            'to' => -777,
          ],
        ],
      ],
    ];
  }

}
