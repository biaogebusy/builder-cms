<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d6\RangeFieldFormatterSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d6\RangeFieldFormatterSettings
 * @group range
 */
class RangeFieldFormatterSettingsTest extends UnitTestCase {

  /**
   * Test the range field formatter settings transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($display_type, $format, $expected) {
    $plugin = new RangeFieldFormatterSettings([], '', [], $this->createMock(MigrationInterface::class));
    $row = $this->createMock(Row::class);
    $row->expects(self::once())
      ->method('getDestinationProperty')
      ->willReturn($display_type);
    $actual = $plugin->transform([NULL, $format], $this->createMock(MigrateExecutableInterface::class), $row, NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'range_decimal default' => [
        'range_decimal', 'default',
        [
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal us_0' => [
        'range_decimal', 'us_0',
        [
          'scale' => 0,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal us_1' => [
        'range_decimal', 'us_1',
        [
          'scale' => 1,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal us_2' => [
        'range_decimal', 'us_2',
        [
          'scale' => 2,
          'decimal_separator' => '.',
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal be_0' => [
        'range_decimal', 'be_0',
        [
          'scale' => 0,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal be_1' => [
        'range_decimal', 'be_1',
        [
          'scale' => 1,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal be_2' => [
        'range_decimal', 'be_2',
        [
          'scale' => 2,
          'decimal_separator' => ',',
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal fr_0' => [
        'range_decimal', 'fr_0',
        [
          'scale' => 0,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal fr_1' => [
        'range_decimal', 'fr_1',
        [
          'scale' => 1,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal fr_2' => [
        'range_decimal', 'fr_2',
        [
          'scale' => 2,
          'decimal_separator' => ',',
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_decimal non-existing' => [
        'range_decimal', 'non-existing',
        [],
      ],
      'range_integer default' => [
        'range_integer', 'default',
        [
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_integer us_0' => [
        'range_integer', 'us_0',
        [
          'thousand_separator' => ',',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_integer be_0' => [
        'range_integer', 'be_0',
        [
          'thousand_separator' => '.',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_integer fr_0' => [
        'range_integer', 'fr_0',
        [
          'thousand_separator' => ' ',
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_integer non-existing' => [
        'range_integer', 'non-existing',
        [],
      ],
      'range_unformatted unformatted' => [
        'range_unformatted', 'unformatted',
        [
          'field_prefix_suffix' => TRUE,
        ],
      ],
      'range_unformatted non-existing' => [
        'range_unformatted', 'non-existing',
        [],
      ],
      'non-existing default' => [
        'non-existing', 'default',
        [],
      ],
      'non-existing non-existing' => [
        'non-existing', 'non-existing',
        [],
      ],
    ];
  }

}
