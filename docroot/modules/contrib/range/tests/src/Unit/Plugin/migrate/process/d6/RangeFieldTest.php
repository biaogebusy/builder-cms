<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\process\d6\RangeField;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\process\d6\RangeField
 * @group range
 */
class RangeFieldTest extends UnitTestCase {

  /**
   * Test the range field data transformations.
   *
   * @covers ::transform
   * @dataProvider transformDataProvider
   */
  public function testTransform($value, $expected) {
    $plugin = new RangeField([], '', [], $this->createMock(MigrationInterface::class));
    $actual = $plugin->transform($value, $this->createMock(MigrateExecutableInterface::class), $this->createMock(Row::class), NULL);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testTransform.
   */
  public function transformDataProvider() {
    return [
      'single' => [
        [
          ['value' => 100],
        ],
        [
          ['from' => 100, 'to' => 100],
        ],
      ],
      'multiple' => [
        [
          ['value' => -10.5],
          ['value' => 0],
          ['value' => 10.5],
          ['value' => 7777777],
        ],
        [
          ['from' => -10.5, 'to' => -10.5],
          ['from' => 0, 'to' => 0],
          ['from' => 10.5, 'to' => 10.5],
          ['from' => 7777777, 'to' => 7777777],
        ],
      ],
    ];
  }

}
