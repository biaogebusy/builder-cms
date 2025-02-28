<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d7;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d7\RangeFieldInstanceWidgetSettings;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d7\RangeFieldInstanceWidgetSettings
 * @group range
 */
class RangeFieldInstanceWidgetSettingsTest extends UnitTestCase {

  /**
   * Test the range field widget settings transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($instance_settings, $expected) {
    $plugin = new RangeFieldInstanceWidgetSettings([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform($instance_settings, $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'empty labels' => [
        [
          'from' => ['label' => ''],
          'to' => ['label' => ''],
        ],
        [
          'label' => ['from' => '', 'to' => ''],
          'placeholder' => ['from' => '', 'to' => ''],
        ],
      ],
      'not empty labels' => [
        [
          'from' => ['label' => 'FROM'],
          'to' => ['label' => 'TO'],
        ],
        [
          'label' => ['from' => 'FROM', 'to' => 'TO'],
          'placeholder' => ['from' => '', 'to' => ''],
        ],
      ],
    ];
  }

}
