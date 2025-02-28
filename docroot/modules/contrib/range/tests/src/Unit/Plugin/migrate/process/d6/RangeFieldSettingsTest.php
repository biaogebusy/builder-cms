<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d6\RangeFieldSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d6\RangeFieldSettings
 * @group range
 */
class RangeFieldSettingsTest extends UnitTestCase {

  /**
   * Test the range field settings transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($field_type, $global_settings, $expected) {
    $plugin = new RangeFieldSettings([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform([$field_type, $global_settings], $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'range_decimal: precision/scale are set' => [
        'range_decimal',
        [
          'precision' => 12,
          'scale' => 1,
        ],
        [
          'precision' => 12,
          'scale' => 1,
        ],
      ],
      'range_decimal: extra settings present' => [
        'range_decimal',
        [
          'precision' => 15,
          'scale' => 0,
          'prefix' => '',
          'suffix' => '',
          'allowed_values_php' => '',
        ],
        [
          'precision' => 15,
          'scale' => 0,
        ],
      ],
      'range_float' => [
        'range_float',
        [
          'precision' => 10,
          'scale' => 2,
          'prefix' => '',
          'suffix' => '',
          'allowed_values_php' => '',
        ],
        [],
      ],
      'range_integer' => [
        'range_integer',
        [
          'precision' => 10,
          'scale' => 2,
          'prefix' => '',
          'suffix' => '',
          'allowed_values_php' => '',
        ],
        [],
      ],
    ];
  }

}
