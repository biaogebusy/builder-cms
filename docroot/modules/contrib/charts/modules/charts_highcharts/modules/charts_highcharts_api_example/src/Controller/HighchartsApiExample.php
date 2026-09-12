<?php

namespace Drupal\charts_highcharts_api_example\Controller;

use Drupal\charts_api_example\ChartExampleBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts API examples rendered with the Highcharts library.
 *
 * The library-agnostic examples come from the shared ChartExampleBuilder; this
 * controller only adds the demonstrations that are specific to Highcharts.
 */
class HighchartsApiExample extends ControllerBase {

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
   * Displays the Highcharts examples.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $library = 'highcharts';
    $build = $this->exampleBuilder->build($library);

    // Radar chart: Highcharts renders this as a polar line chart.
    $build['content']['radar'] = $this->exampleBuilder->baseChart($library, 'line', $this->t('Highcharts Radar Chart'));
    $build['content']['radar'] += [
      'series' => $this->exampleBuilder->defaultSeries(),
      '#polar' => TRUE,
    ];

    // Boxplot chart. Highcharts expects each point as
    // [min, Q1, median, Q3, max].
    $build['content']['boxplot'] = $this->exampleBuilder->buildBoxplotExample($library, [
      [1, 2, 3, 4, 5],
      [2, 3, 4, 5, 6],
      [3, 4, 5, 6, 7],
      [4, 5, 6, 7, 8],
    ]);

    // Heatmap chart. Highcharts expects each point as [x, y, value] and needs
    // the heatmap module asset attached.
    $heatmap_chart_metadata = [
      'series' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Heatmap'),
        '#data' => [
          [0, 0, 23], [0, 1, 45], [0, 2, 17], [0, 3, 56], [0, 4, 39],
          [1, 0, 61], [1, 1, 87], [1, 2, 42], [1, 3, 76], [1, 4, 105],
          [2, 0, 94], [2, 1, 37], [2, 2, 68], [2, 3, 112], [2, 4, 29],
          [3, 0, 41], [3, 1, 83], [3, 2, 69], [3, 3, 54], [3, 4, 97],
          [4, 0, 76], [4, 1, 22], [4, 2, 51], [4, 3, 79], [4, 4, 63],
          [5, 0, 43], [5, 1, 91], [5, 2, 67], [5, 3, 33], [5, 4, 72],
          [6, 0, 58], [6, 1, 101], [6, 2, 45], [6, 3, 27], [6, 4, 53],
          [7, 0, 84], [7, 1, 32], [7, 2, 46], [7, 3, 79], [7, 4, 88],
          [8, 0, 37], [8, 1, 62], [8, 2, 71], [8, 3, 113], [8, 4, 49],
          [9, 0, 92], [9, 1, 58], [9, 2, 84], [9, 3, 29], [9, 4, 66],
        ],
      ],
      'x_axis' => [
        '#type' => 'chart_xaxis',
        '#title' => $this->t('X-Axis'),
        '#labels' => [
          $this->t('January 2021'),
          $this->t('February 2021'),
          $this->t('March 2021'),
          $this->t('April 2021'),
          $this->t('May 2021'),
          $this->t('June 2021'),
          $this->t('July 2021'),
          $this->t('August 2021'),
          $this->t('September 2021'),
          $this->t('October 2021'),
        ],
      ],
      '#attached' => [
        'library' => ['charts_highcharts/heatmap'],
      ],
      '#raw_options' => [
        'colorAxis' => [
          'minColor' => '#FFFFFF',
          'min' => 0,
        ],
        'plotOptions' => [
          'series' => [
            'dataLabels' => ['enabled' => TRUE],
            'marker' => ['enabled' => TRUE],
          ],
        ],
      ],
    ];
    $build['content']['heatmap'] = $heatmap_chart_metadata + $this->exampleBuilder->baseChart($library, 'heatmap', $this->t('Highcharts Heatmap Chart'));

    // Range area chart. Highcharts expects each point as [low, high].
    $build['content']['range_area'] = $this->exampleBuilder->buildRangeAreaExample($library, [
      [6, 10],
      [5, 7],
      [3, 7],
      [4, 9],
    ]);

    // Highcharts-specific: alter the chart definition from a PHP hook
    // (see
    // \Drupal\charts_highcharts_api_example\Hook\HighchartsApiExampleHooks).
    $build['content']['php_override'] = $this->exampleBuilder->baseChart($library, 'column', $this->t('Highcharts Chart, Overridden By PHP Hook'));
    $build['content']['php_override'] += [
      '#chart_id' => 'example_id_php',
      'series' => $this->exampleBuilder->defaultSeries(),
      '#color_changer' => TRUE,
    ];

    // Highcharts-specific: alter the rendered chart from a JS function.
    $build['content']['js_override'] = $this->exampleBuilder->baseChart($library, 'column', $this->t('Highcharts Chart, Overridden By JS Function'));
    $build['content']['js_override'] += [
      '#id' => 'exampleidjs',
      '#chart_id' => 'example_id_js_chart',
      'series' => $this->exampleBuilder->defaultSeries(),
    ];

    return $build;
  }

}
