<?php

namespace Drupal\Tests\charts\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the 'chart_config' field type.
 *
 * @group charts
 */
class ChartConfigItemTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['charts', 'entity_test', 'field', 'user', 'system'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field']);

    // Create a chart_config field.
    FieldStorageConfig::create([
      'field_name' => 'field_chart',
      'entity_type' => 'entity_test',
      'type' => 'chart_config',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_chart',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests the isEmpty() method.
   *
   * @dataProvider isEmptyDataProvider
   */
  public function testIsEmpty($value, $expected, $message) {
    $entity = EntityTest::create();
    $entity->field_chart = $value;
    $this->assertEquals($expected, $entity->field_chart->isEmpty(), $message);
  }

  /**
   * Data provider for testIsEmpty().
   *
   * @return array
   *   The data provider test cases.
   */
  public static function isEmptyDataProvider(): array {
    return [
      'config is empty' => [
        [
          'config' => [],
          'library' => 'highcharts',
          'type' => 'line',
        ],
        TRUE,
        'Field is empty when config is empty.',
      ],
      'config has no data collector table' => [
        [
          'config' => [
            'library' => 'highcharts',
            'type' => 'line',
          ],
        ],
        FALSE,
        'Field is not empty when config has chart settings.',
      ],
      'config has an empty data collector table' => [
        [
          'config' => [
            'series' => [
              'data_collector_table' => [
                [['data' => '']],
              ],
            ],
          ],
        ],
        FALSE,
        'Field is not empty when config exists, even without data.',
      ],
      'config has data in data collector table' => [
        [
          'config' => [
            'series' => [
              'data_collector_table' => [
                [['data' => '10']],
              ],
            ],
          ],
        ],
        FALSE,
        'Field is not empty when data collector table has data.',
      ],
    ];
  }

}
