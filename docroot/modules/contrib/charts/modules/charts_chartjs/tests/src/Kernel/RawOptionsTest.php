<?php

namespace Drupal\Tests\charts_chartjs\Kernel;

use Drupal\Tests\charts\Kernel\ChartsKernelTestBase;

/**
 * Tests the raw_options element property behavior.
 *
 * @group charts
 */
class RawOptionsTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_chartjs',
  ];

  /**
   * Test that the raw options settings can override main definition.
   */
  public function testRawOptionsOverride() {
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'bar',
      '#stacking' => 1,
      '#raw_options' => [
        'options' => [
          'scales' => [
            'x' => ['stacked' => FALSE],
          ],
        ],
      ],
    ];

    $path = ['options', 'scales', 'x', 'stacked'];
    $this->assertJsonPropertyHasValue($element, $path, FALSE);
  }

}
