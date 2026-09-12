<?php

namespace Drupal\charts_billboard_api_example\Controller;

use Drupal\charts_api_example\ChartExampleBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts API examples rendered with the Billboard.js library.
 *
 * The library-agnostic examples come from the shared ChartExampleBuilder; this
 * controller adds only the Billboard.js-specific demonstrations.
 */
class BillboardApiExample extends ControllerBase {

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
   * Displays the Billboard.js examples.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $library = 'billboard';
    $build = $this->exampleBuilder->build($library);

    // Billboard.js-specific: cull dense x-axis ticks on the CSV example.
    if (isset($build['content']['from_csv_file'])) {
      $build['content']['from_csv_file']['#raw_options'] = [
        'axis' => [
          'x' => [
            'tick' => [
              'culling' => TRUE,
            ],
          ],
        ],
      ];
    }

    // Radar chart: Billboard.js renders the polar display setting as a radar.
    $build['content']['radar'] = $this->exampleBuilder->buildPolarExample($library);

    // Candlestick chart. Billboard.js expects [open, high, low, close].
    $build['content']['candlestick'] = $this->exampleBuilder->buildCandlestickExample($library, [
      [20, 38, 10, 34],
      [40, 50, 30, 35],
      [31, 44, 33, 38],
      [38, 42, 5, 15],
    ]);

    // Range area chart. Billboard.js expects [low, mid, high].
    $build['content']['range_area'] = $this->exampleBuilder->buildRangeAreaExample($library, [
      [6, 8, 10],
      [5, 6, 7],
      [3, 5, 7],
      [4, 6, 9],
    ]);

    return $build;
  }

}
