<?php

namespace Drupal\charts_chartjs_api_example\Controller;

use Drupal\charts_api_example\ChartExampleBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts API examples rendered with the Chart.js library.
 *
 * The library-agnostic examples come from the shared ChartExampleBuilder, which
 * already omits anything Chart.js does not support. This controller adds only
 * the Chart.js-specific demonstrations.
 */
class ChartjsApiExample extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\charts_api_example\ChartExampleBuilder $exampleBuilder
   *   The shared chart example builder.
   */
  public function __construct(protected ChartExampleBuilder $exampleBuilder) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('charts_api_example.builder'));
  }

  /**
   * Displays the Chart.js examples.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $library = 'chartjs';
    $build = $this->exampleBuilder->build($library);

    // Chart.js-specific: a real-life #raw_options use-case. Auto-skip dense
    // x-axis ticks on the CSV example.
    if (isset($build['content']['from_csv_file'])) {
      $build['content']['from_csv_file']['#raw_options'] = [
        'options' => [
          'scales' => [
            'x' => [
              'ticks' => [
                'autoSkip' => TRUE,
              ],
            ],
          ],
        ],
      ];
    }

    // Radar chart: Chart.js renders the polar display setting as a radar.
    $build['content']['radar'] = $this->exampleBuilder->buildPolarExample($library);

    // Polar area chart: a Chart.js native polar type.
    $tooltips = $this->config('charts.settings')->get('charts_default_settings.display.tooltips');
    $build['content']['polar_area'] = [
      '#type' => 'chart',
      '#chart_library' => $library,
      '#tooltips' => $tooltips,
      '#title' => $this->t('Chart.js Polar Area Chart'),
      '#chart_type' => 'polarArea',
      'series' => [
        '#type' => 'chart_data',
        '#title' => $this->t('5.0.x'),
        '#data' => [257, 235, 325, 340],
      ],
      'x_axis' => $this->exampleBuilder->defaultXaxis(),
      'y_axis' => $this->exampleBuilder->defaultYaxis(),
      '#accessible_table' => 'collapsible',
      '#raw_options' => [],
    ];

    return $build;
  }

}
