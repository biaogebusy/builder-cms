<?php

declare(strict_types=1);

namespace Drupal\charts_highcharts_api_example\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Charts Highcharts API Example module.
 */
class HighchartsApiExampleHooks {

  /**
   * Implements hook_chart_definition_CHART_ID_alter().
   *
   * Alters the 'example_id_php' chart definition. This is Highcharts-specific:
   * 'chart.backgroundColor' is a Highcharts option.
   *
   * @param array $chart
   *   The chart definition.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('chart_definition_example_id_php_alter')]
  public function chartDefinitionExampleIdPhpAlter(array &$chart): void {
    $chart['chart']['backgroundColor'] = 'blue';
  }

  /**
   * Implements hook_chart_alter().
   *
   * Attaches the JS override library to the 'example_id_js_chart' chart.
   *
   * @param array $element
   *   The chart element.
   * @param string|null $chart_id
   *   The chart ID.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('chart_alter')]
  public function chartAlter(array &$element, ?string $chart_id): void {
    if ($chart_id === 'example_id_js_chart') {
      $element['#attached']['library'][] = 'charts_highcharts_api_example/override';
    }
  }

}
