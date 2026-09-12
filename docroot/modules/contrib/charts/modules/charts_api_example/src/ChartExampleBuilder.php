<?php

declare(strict_types=1);

namespace Drupal\charts_api_example;

use Drupal\charts\ChartManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds a set of canonical, library-agnostic chart examples.
 *
 * Each charts_*_api_example submodule calls build() for its own library and
 * then layers on any library-specific extras. Examples are included only for
 * chart types the library declares support for (via isSupportedChartType()),
 * which removes the need for per-library "if" branching in the example code.
 */
class ChartExampleBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a ChartExampleBuilder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\charts\ChartManager $chartManager
   *   The charts library plugin manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleList
   *   The module extension list.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ChartManager $chartManager,
    protected ModuleExtensionList $moduleList,
  ) {}

  /**
   * Builds the canonical examples supported by the given library.
   *
   * @param string $library
   *   The charts library plugin ID (e.g. "highcharts").
   *
   * @return array
   *   A render array container of chart examples, keyed under 'content'. The
   *   caller may add to or override any entry before returning it.
   */
  public function build(string $library): array {
    $settings = $this->configFactory->get('charts.settings');
    $tooltips = $settings->get('charts_default_settings.display.tooltips');

    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
    $plugin = $this->chartManager->createInstance($library);
    $label = ucfirst($library);

    $series = $this->defaultSeries();

    $container = [
      '#type' => 'container',
      'content' => [],
    ];

    // One example per simple, single-series type the library supports.
    foreach (['area', 'bar', 'column', 'line', 'spline', 'pie', 'donut'] as $type) {
      if (!$plugin->isSupportedChartType($type)) {
        continue;
      }
      $container['content'][$type] = $this->baseChart($library, $type, $this->t('@library @type Chart', [
        '@library' => $label,
        '@type' => ucfirst($type),
      ]));
      $container['content'][$type]['series'] = $series;
    }

    // Column chart with two series.
    if ($plugin->isSupportedChartType('column')) {
      $container['content']['two_series_column'] = $this->baseChart($library, 'column', $this->t('@library Column Chart (Two Series)', ['@library' => $label]));
      $container['content']['two_series_column'] += [
        'series_one' => $series,
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('8.x-3.x'),
          '#data' => [4330, 4413, 4212, 4431],
          '#color' => '#77b259',
        ],
      ];
    }

    // Stacked column chart with two series.
    if ($plugin->isSupportedChartType('column')) {
      $container['content']['stacked_two_series_column'] = $this->baseChart($library, 'column', $this->t('@library Stacked Column Chart (Two Series)', ['@library' => $label]));
      $container['content']['stacked_two_series_column'] += [
        'series_one' => $series,
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('8.x-3.x'),
          '#data' => [4330, 4413, 4212, 4431],
          '#color' => '#77b259',
        ],
        '#stacking' => TRUE,
      ];
    }

    // Combination chart (column and line). Requires both types.
    if ($plugin->isSupportedChartType('column') && $plugin->isSupportedChartType('line')) {
      $container['content']['combo'] = $this->baseChart($library, 'column', $this->t('@library Combination Chart', ['@library' => $label]));
      $container['content']['combo'] += [
        'series_one' => $series,
        'series_two' => [
          '#type' => 'chart_data',
          '#chart_type' => 'line',
          '#title' => $this->t('8.x-3.x'),
          '#data' => [4330, 4413, 4212, 4431],
          '#color' => '#77b259',
        ],
      ];

      // Combination chart with a secondary Y-axis.
      $container['content']['combo_dual_yaxes'] = $this->baseChart($library, 'column', $this->t('@library Combination Chart with Secondary Y-Axis', ['@library' => $label]));
      $container['content']['combo_dual_yaxes'] += [
        'series_one' => $series,
        'series_two' => [
          '#type' => 'chart_data',
          '#chart_type' => 'line',
          '#title' => $this->t('8.x-3.x'),
          '#data' => [4330, 4413, 4212, 4431],
          '#color' => '#77b259',
          '#target_axis' => 'y_axis_secondary',
        ],
        'y_axis_secondary' => [
          '#type' => 'chart_yaxis',
          '#title' => $this->t('Secondary Y-Axis'),
          '#opposite' => TRUE,
        ],
      ];
    }

    // Stacked area chart from a local CSV file.
    if ($plugin->isSupportedChartType('area')) {
      $csv = $this->getCsvContents();
      $container['content']['from_csv_file'] = $this->baseChart($library, 'area', $this->t('@library Stacked Area Chart from CSV File', ['@library' => $label]));
      $container['content']['from_csv_file'] += [
        'series_seven_2' => [
          '#type' => 'chart_data',
          '#title' => $this->t('7.x-2.x'),
          // Reversed because the fixture is ordered desc rather than asc.
          '#data' => array_reverse($csv['7.x-2.x']),
          '#color' => '#76b7b2',
        ],
        'series_eight_three' => [
          '#type' => 'chart_data',
          '#title' => $this->t('8.x-3.x'),
          '#data' => array_reverse($csv['8.x-3.x']),
          '#color' => '#edc949',
        ],
        'series_five_zero' => [
          '#type' => 'chart_data',
          '#title' => $this->t('5.0.x'),
          '#data' => array_reverse($csv['5.0.x']),
          '#color' => '#ff9da7',
        ],
        '#stacking' => TRUE,
      ];
      $container['content']['from_csv_file']['x_axis'] = [
        '#type' => 'chart_xaxis',
        '#title' => $this->t('Week'),
        '#labels' => array_reverse($csv['Week']),
      ];
    }

    // Gauge chart.
    if ($plugin->isSupportedChartType('gauge')) {
      $container['content']['gauge'] = $this->baseChart($library, 'gauge', $this->t('@library Gauge Chart', ['@library' => $label]));
      $container['content']['gauge'] += [
        '#gauge' => [
          'green_to' => 100,
          'green_from' => 75,
          'yellow_to' => 74,
          'yellow_from' => 50,
          'red_to' => 49,
          'red_from' => 0,
          'max' => 100,
          'min' => 0,
        ],
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Speed'),
          '#data' => [65],
        ],
      ];
    }

    // Scatter chart.
    if ($plugin->isSupportedChartType('scatter')) {
      $container['content']['scatter'] = $this->baseChart($library, 'scatter', $this->t('@library Scatter Chart', ['@library' => $label]));
      $container['content']['scatter'] = array_merge($container['content']['scatter'], [
        '#data_markers' => TRUE,
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Group 1'),
          '#data' => [[162.2, 51.8], [164.5, 58.0], [160.5, 49.6], [154.0, 65.0]],
        ],
        'x_axis' => [
          '#type' => 'chart_xaxis',
          '#title' => $this->t('Height'),
          '#labels' => [],
        ],
        'y_axis' => [
          '#type' => 'chart_yaxis',
          '#title' => $this->t('Weight'),
        ],
      ]);
    }

    // Bubble chart.
    if ($plugin->isSupportedChartType('bubble')) {
      $container['content']['bubble'] = $this->baseChart($library, 'bubble', $this->t('@library Bubble Chart', ['@library' => $label]));
      $container['content']['bubble'] = array_merge($container['content']['bubble'], [
        '#data_markers' => TRUE,
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Group 1'),
          '#data' => [[162.2, 51.8, 10], [164.5, 58.0, 20], [160.5, 49.6, 30], [154.0, 65.0, 40]],
        ],
        'x_axis' => [
          '#type' => 'chart_xaxis',
          '#title' => $this->t('Height'),
          '#labels' => [],
        ],
        'y_axis' => [
          '#type' => 'chart_yaxis',
          '#title' => $this->t('Weight'),
        ],
      ]);
    }

    // Treemap chart.
    if ($plugin->isSupportedChartType('treemap')) {
      $container['content']['treemap'] = $this->baseChart($library, 'treemap', $this->t('@library Treemap Chart', ['@library' => $label]));
      $container['content']['treemap']['series'] = [
        '#type' => 'chart_data',
        '#title' => $this->t('Treemap'),
        '#data' => [10, 30, 60, 80],
      ];
    }

    return $container;
  }

  /**
   * Builds a radar example using the polar display setting.
   *
   * "Radar" is not a distinct chart type: it is a cartesian chart with the
   * "Transform cartesian charts into the polar coordinate system" setting
   * (#polar) enabled. Highcharts renders a polar line; Chart.js renders a
   * radar. Only libraries that honour the polar setting (not C3 or Google)
   * should include this example, which is why it is a separate opt-in helper
   * rather than part of build().
   *
   * @param string $library
   *   The charts library plugin ID.
   *
   * @return array
   *   A chart render array.
   */
  public function buildPolarExample(string $library): array {
    $chart = $this->baseChart($library, 'line', $this->t('@library Radar Chart (polar coordinate system)', ['@library' => ucfirst($library)]));
    $chart += [
      '#polar' => TRUE,
      'series' => $this->defaultSeries(),
    ];
    return $chart;
  }

  /**
   * Builds a boxplot example. Data points are [min, Q1, median, Q3, max].
   *
   * @param string $library
   *   The charts library plugin ID.
   * @param array $data
   *   The boxplot data.
   *
   * @return array
   *   A chart render array.
   */
  public function buildBoxplotExample(string $library, array $data): array {
    $chart = $this->baseChart($library, 'boxplot', $this->t('@library Boxplot Chart', ['@library' => ucfirst($library)]));
    $chart['series'] = [
      '#type' => 'chart_data',
      '#title' => $this->t('Boxplot'),
      '#data' => $data,
    ];
    return $chart;
  }

  /**
   * Builds a candlestick example.
   *
   * The point order depends on the library (for example Highstock uses
   * [open, high, low, close] while Google uses [low, open, close, high]), so
   * the caller supplies the correctly-shaped data.
   *
   * @param string $library
   *   The charts library plugin ID.
   * @param array $data
   *   The candlestick data in the library's expected point order.
   *
   * @return array
   *   A chart render array.
   */
  public function buildCandlestickExample(string $library, array $data): array {
    $chart = $this->baseChart($library, 'candlestick', $this->t('@library Candlestick Chart', ['@library' => ucfirst($library)]));
    $chart['series'] = [
      '#type' => 'chart_data',
      '#title' => $this->t('Candlestick'),
      '#data' => $data,
    ];
    return $chart;
  }

  /**
   * Builds a range area (arearange) example.
   *
   * The point shape depends on the library (for example Highcharts uses
   * [low, high] while Billboard.js uses [low, mid, high]), so the caller
   * supplies the correctly-shaped data.
   *
   * @param string $library
   *   The charts library plugin ID.
   * @param array $data
   *   The range data in the library's expected point shape.
   *
   * @return array
   *   A chart render array.
   */
  public function buildRangeAreaExample(string $library, array $data): array {
    $chart = $this->baseChart($library, 'arearange', $this->t('@library Range Area Chart', ['@library' => ucfirst($library)]));
    $chart['series'] = [
      '#type' => 'chart_data',
      '#title' => $this->t('Range Area'),
      '#data' => $data,
    ];
    return $chart;
  }

  /**
   * Returns a basic chart render array.
   *
   * @param string $library
   *   The charts library plugin ID.
   * @param string $type
   *   The chart type.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The chart title.
   *
   * @return array
   *   A chart render array.
   */
  public function baseChart(string $library, string $type, $title): array {
    return [
      '#type' => 'chart',
      '#chart_library' => $library,
      '#tooltips' => $this->tooltipsSetting(),
      '#title' => $title,
      '#chart_type' => $type,
      'x_axis' => $this->defaultXaxis(),
      'y_axis' => $this->defaultYaxis(),
      '#accessible_table' => 'collapsible',
      '#raw_options' => [],
    ];
  }

  /**
   * Returns the configured default for the tooltips display setting.
   *
   * @return mixed
   *   The tooltips setting value.
   */
  protected function tooltipsSetting() {
    return $this->configFactory->get('charts.settings')->get('charts_default_settings.display.tooltips');
  }

  /**
   * A single data series reused across several examples.
   *
   * @return array
   *   A chart_data render array.
   */
  public function defaultSeries(): array {
    return [
      '#type' => 'chart_data',
      '#title' => $this->t('5.0.x'),
      '#data' => [257, 235, 325, 340],
      '#color' => '#1d84c3',
    ];
  }

  /**
   * The x-axis reused across several examples.
   *
   * @return array
   *   A chart_xaxis render array.
   */
  public function defaultXaxis(): array {
    return [
      '#type' => 'chart_xaxis',
      '#title' => $this->t('Months'),
      '#labels' => [
        $this->t('January 2021'),
        $this->t('February 2021'),
        $this->t('March 2021'),
        $this->t('April 2021'),
      ],
    ];
  }

  /**
   * The y-axis reused across several examples.
   *
   * @return array
   *   A chart_yaxis render array.
   */
  public function defaultYaxis(): array {
    return [
      '#type' => 'chart_yaxis',
      '#title' => $this->t('Number of Installs'),
    ];
  }

  /**
   * Returns the CSV fixture contents organized by column.
   *
   * @return array
   *   The array of columns keyed by header.
   */
  protected function getCsvContents(): array {
    $file_path = $this->moduleList->getPath('charts_api_example');
    $file_name = $file_path . '/fixtures/charts_api_example_file.csv';
    $handle = fopen($file_name, 'r');
    $all_rows = [];
    while ($row = fgetcsv($handle, NULL, ',', '"', '')) {
      $all_rows['Week'][] = $row[0];
      $all_rows['7.x-2.x'][] = (int) $row[4];
      $all_rows['8.x-3.x'][] = (int) $row[6];
      $all_rows['5.0.x'][] = (int) $row[8];
    }
    fclose($handle);

    return $all_rows;
  }

}
