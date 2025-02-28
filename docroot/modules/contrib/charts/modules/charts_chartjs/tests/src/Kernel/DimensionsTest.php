<?php

namespace Drupal\Tests\charts_chartjs\Kernel;

use Drupal\Tests\charts\Kernel\DimensionsTest as BaseDimensionsTest;

/**
 * Test the dimensions element property behavior for the chartjs library.
 *
 * @group charts
 */
class DimensionsTest extends BaseDimensionsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_chartjs',
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
    $element['#chart_library'] = 'chartjs';
    $styles = $this->getChartStyle($element, '[data-chartjs-render-wrapper]');
    $width = $styles['width'] ?? NULL;
    $height = $styles['height'] ?? NULL;
    $this->assertEquals($expected_width, $width);
    $this->assertEquals($expected_height, $height);
  }

}
