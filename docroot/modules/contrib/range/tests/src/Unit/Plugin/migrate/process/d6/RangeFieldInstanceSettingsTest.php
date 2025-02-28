<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d6\RangeFieldInstanceSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d6\RangeFieldInstanceSettings
 * @group range
 */
class RangeFieldInstanceSettingsTest extends UnitTestCase {

  /**
   * Test the range field instance settings transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($field_settings, $expected) {
    $plugin = new RangeFieldInstanceSettings([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform([[], [], $field_settings], $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'min/max and prefix/suffix are set' => [
        [
          'min' => 5,
          'max' => 50,
          'prefix' => 'PREFIX',
          'suffix' => 'SUFFIX',
        ],
        [
          'min' => 5,
          'max' => 50,
          'field' => ['prefix' => 'PREFIX', 'suffix' => 'SUFFIX'],
        ],
      ],
      'min/max set to infinity and no prefix/suffix' => [
        [
          'min' => '<',
          'max' => '>',
          'prefix' => '',
          'suffix' => '',
        ],
        [
          'min' => NULL,
          'max' => NULL,
          'field' => ['prefix' => '', 'suffix' => ''],
        ],
      ],
      'extra settings present' => [
        [
          'min' => -5.5,
          'max' => 5.5,
          'prefix' => '',
          'suffix' => '',
          'allowed_values_php' => '',
        ],
        [
          'min' => -5.5,
          'max' => 5.5,
          'field' => ['prefix' => '', 'suffix' => ''],
        ],
      ],
    ];
  }

}
