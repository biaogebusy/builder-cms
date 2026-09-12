<?php

namespace Drupal\charts_google_api_example\Controller;

use Drupal\charts_api_example\ChartExampleBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts API examples rendered with the Google Charts library.
 *
 * The library-agnostic examples come from the shared ChartExampleBuilder; this
 * controller adds only the Google-specific demonstrations. Google does not
 * support the polar display setting, so there is no radar example here.
 */
class GoogleApiExample extends ControllerBase {

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
   * Displays the Google Charts examples.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $library = 'google';
    $build = $this->exampleBuilder->build($library);

    // Boxplot chart with the styling Google Charts needs to render whiskers.
    $build['content']['boxplot'] = $this->exampleBuilder->buildBoxplotExample($library, [
      [1, 2, 3, 4, 5],
      [2, 3, 4, 5, 6],
      [3, 4, 5, 6, 7],
      [4, 5, 6, 7, 8],
    ]);
    $build['content']['boxplot']['#raw_options'] = [
      'options' => [
        'lineWidth' => 0,
        'legend' => ['position' => 'none'],
        'series' => [
          0 => ['color' => '#1A8763', 'visibleInLegend' => FALSE],
        ],
        'intervals' => [
          'style' => 'boxes',
          'barWidth' => 1,
          'boxWidth' => 1,
          'lineWidth' => 2,
          'color' => '#76A7FA',
        ],
        'interval' => [
          'max' => ['style' => 'bars', 'fillOpacity' => 1, 'color' => '#777'],
          'min' => ['style' => 'bars', 'fillOpacity' => 1, 'color' => '#777'],
        ],
      ],
    ];

    // Candlestick chart. Google Charts expects [low, open, close, high].
    $build['content']['candlestick'] = $this->exampleBuilder->buildCandlestickExample($library, [
      [10, 20, 34, 38],
      [30, 40, 35, 50],
      [33, 31, 38, 44],
      [5, 38, 15, 42],
    ]);

    return $build;
  }

}
