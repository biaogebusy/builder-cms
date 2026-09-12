<?php

namespace Drupal\Tests\charts_api_example\Kernel;

use Drupal\Tests\charts\Kernel\ChartsKernelTestBase;

/**
 * Tests that ChartExampleBuilder gates examples by a library's supported types.
 *
 * This uses the charts_test library, which declares a fixed subset of chart
 * types, so the test stays decoupled from any real charting library and keeps
 * working as the bundled libraries are extracted into their own projects.
 *
 * The per-library example pages, including the library-specific extras added
 * in each controller (boxplot, heatmap, radar, etc.), are tested in each
 * library's own example module, which travels with the library.
 *
 * @group charts
 */
class ChartExampleBuilderTest extends ChartsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'charts',
    'charts_api_example',
    'charts_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['charts']);
  }

  /**
   * The builder includes an example only when the library supports the type.
   */
  public function testExamplesAreGatedBySupportedTypes(): void {
    /** @var \Drupal\charts_api_example\ChartExampleBuilder $builder */
    $builder = $this->container->get('charts_api_example.builder');
    $build = $builder->build('charts_test_library');
    $keys = array_keys($build['content'] ?? []);

    // The test library declares: area, bar, bubble, column, donut, gauge,
    // line, pie, scatter. Examples for those types must be present.
    foreach ([
      'area',
      'bar',
      'column',
      'line',
      'pie',
      'donut',
      'gauge',
      'scatter',
      'bubble',
    ] as $key) {
      $this->assertContains($key, $keys, "Expected the '$key' example for a supporting library.");
    }

    // The combination examples require both column and line, both supported.
    foreach ([
      'two_series_column',
      'stacked_two_series_column',
      'combo',
      'combo_dual_yaxes',
      'from_csv_file',
    ] as $key) {
      $this->assertContains($key, $keys, "Expected the '$key' example for a supporting library.");
    }

    // The test library does not declare 'spline', so the builder must omit it.
    // This is the core gating guarantee.
    $this->assertNotContains('spline', $keys);

    // Every built example is rendered with the requested library.
    foreach ($build['content'] as $example) {
      $this->assertSame('charts_test_library', $example['#chart_library']);
    }
  }

}
