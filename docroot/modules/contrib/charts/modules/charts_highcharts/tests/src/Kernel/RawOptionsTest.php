<?php

namespace Drupal\Tests\charts_highcharts\Kernel;

use Drupal\Tests\charts\Kernel\ChartsKernelTestBase;

/**
 * Test the raw_options element property behavior.
 *
 * @group charts
 */
class RawOptionsTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_highcharts',
  ];

  /**
   * Test raw options set on the main chart element.
   */
  public function testMergeDeepArrayInPopulateOptions() {
    $series = [
      '#type' => 'chart_data',
      '#title' => '5.0.x',
      '#data' => [257, 235, 325, 340],
      '#color' => '#1d84c3',
    ];
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'column',
      'series' => $series,
      '#raw_options' => [
        'plotOptions' => [
          'series' => ['grouping' => TRUE],
        ],
      ],
    ];

    // Testing raw options when there is NO data already set in the definition.
    $path = ['plotOptions', 'series', 'grouping'];
    $this->assertJsonPropertyHasValue($element, $path, TRUE);

    // Testing raw options when there is data already set in the definition.
    $element['#data_labels'] = TRUE;
    $element['#raw_options'] = [
      'plotOptions' => [
        'series' => [
          'dataLabels' => [
            'enabled' => FALSE,
          ],
        ],
      ],
    ];
    $path = ['plotOptions', 'series', 'dataLabels', 'enabled'];
    $this->assertJsonPropertyHasValue($element, $path, FALSE);
  }

}
