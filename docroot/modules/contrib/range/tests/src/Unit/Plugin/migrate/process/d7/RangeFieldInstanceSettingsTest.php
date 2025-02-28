<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d7;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d7\RangeFieldInstanceSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d7\RangeFieldInstanceSettings
 * @group range
 */
class RangeFieldInstanceSettingsTest extends UnitTestCase {

  /**
   * Test the range field widget settings transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($instance_settings, $expected) {
    $plugin = new RangeFieldInstanceSettings([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform($instance_settings, $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      [
        [
          [
            'field' => ['prefix' => 'a', 'suffix' => 'b'],
            'from' => ['label' => 'From', 'prefix' => 'c', 'suffix' => 'd'],
            'to' => ['label' => 'to', 'prefix' => 'e', 'suffix' => 'f'],
            'combined' => ['prefix' => 'g', 'suffix' => 'h'],
            'user_register_form' => FALSE,
          ],
        ],
        [
          'field' => ['prefix' => 'a', 'suffix' => 'b'],
          'from' => ['prefix' => 'c', 'suffix' => 'd'],
          'to' => ['prefix' => 'e', 'suffix' => 'f'],
          'combined' => ['prefix' => 'g', 'suffix' => 'h'],
        ],
      ],
    ];
  }

}
