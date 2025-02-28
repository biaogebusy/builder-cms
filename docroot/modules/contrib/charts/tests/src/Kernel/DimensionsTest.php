<?php

namespace Drupal\Tests\charts\Kernel;

/**
 * Test the dimensions element property behavior.
 *
 * @group charts
 */
class DimensionsTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_test',
  ];

  /**
   * Test that the set dimensions are correctly added on the element.
   *
   * @param array $element
   *   The element to be tested.
   * @param string|null $expected_width
   *   The expected width to be found on the element.
   * @param string|null $expected_height
   *   The expected height to be found on the element.
   *
   * @dataProvider provideChartElements
   */
  public function testDimensions(array $element, string $expected_width = NULL, string $expected_height = NULL) {
    $styles = $this->getChartStyle($element);
    $width = $styles['width'] ?? NULL;
    $height = $styles['height'] ?? NULL;
    $this->assertEquals($expected_width, $width);
    $this->assertEquals($expected_height, $height);
  }

  /**
   * Provides chart elements for the test dimensions test.
   *
   * @return array[]
   *   the chart elements.
   */
  public function provideChartElements(): array {
    // A simple chart element with no dimensions set.
    $element = [
      '#type' => 'chart',
      '#chart_type' => 'column',
      'series' => [
        '#type' => 'chart_data',
        '#title' => '5.0.x',
        '#data' => [257, 235, 325, 340],
        '#color' => '#1d84c3',
      ],
    ];
    // Element with the dimensions set.
    $element_filled_dimensions = $element + [
      '#width' => 50,
      '#width_units' => '%',
      '#height' => 225,
      '#height_units' => 'px',
    ];

    return [
      'empty dimensions' => [$element, NULL, NULL],
      'filled dimensions' => [$element_filled_dimensions, '50%', '225px'],
    ];
  }

}
