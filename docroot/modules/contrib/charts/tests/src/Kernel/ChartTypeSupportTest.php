<?php

namespace Drupal\Tests\charts\Kernel;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Tests\charts\Traits\ConfigUpdateTrait;

/**
 * Tests the chart types support functionality.
 *
 * @group charts
 */
class ChartTypeSupportTest extends ChartsKernelTestBase {

  use ConfigUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->updateFooConfiguration('bar');
  }

  /**
   * Tests the chart type support functionality.
   *
   * @param string $chart_type
   *   The chart type to test.
   * @param string $expected_exception
   *   The expected exception.
   * @param string $expected_exception_message
   *   The expected exception message.
   *
   * @dataProvider provideChartTypesData
   */
  public function testNotSupportedChartType(string $chart_type, string $expected_exception, string $expected_exception_message) {
    $element = [
      '#type' => 'chart',
      '#library' => 'charts_test_library',
      '#chart_type' => $chart_type,
    ];

    $this->expectException($expected_exception);
    $this->expectExceptionMessage($expected_exception_message);
    $this->renderer->renderRoot($element);
  }

  /**
   * Provides data for chart types support test.
   *
   * @return array[]
   *   The chart types test cases.
   */
  public function provideChartTypesData(): array {
    return [
      // Asserting that a chart type that is not a valid plugin chart type will
      // throw a plugin not found exception during discovery.
      'chart type plugin does not exist' => [
        'not_supported',
        PluginNotFoundException::class,
        'The "not_supported" plugin does not exist. Valid plugin IDs for Drupal\charts\TypeManager are: area, bar, bubble, column, donut, gauge, line, pie, scatter, spline',
      ],
      // Asserting that a chart type not supported by a chart library plugin
      // will throw a logic exception.
      'chart type plugin exists but not supported by the chart library plugin' => [
        'spline',
        \LogicException::class,
        'The provided chart type "spline" is not supported by "Charts Test Library" chart plugin library.',
      ],
    ];
  }

}
