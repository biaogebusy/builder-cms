<?php

namespace Drupal\charts_api_example\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts Api Example.
 */
class ChartsApiExample extends ControllerBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * Construct.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The UUID service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module list.
   */
  public function __construct(MessengerInterface $messenger, UuidInterface $uuidService, ModuleExtensionList $module_list) {
    $this->messenger = $messenger;
    $this->uuidService = $uuidService;
    $this->moduleList = $module_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('uuid'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Display the dashboard of charts.
   *
   * @return array
   *   Array to render.
   */
  public function display() {

    /*
     * If you do not set a library specifically in your render array, Charts
     * will use your default. But you do need to set your default library.
     */
    $charts_settings = $this->config('charts.settings');
    $library = $charts_settings->get('charts_default_settings.library');
    if (empty($library)) {
      $this->messenger->addError($this->t('You need to first configure Charts default settings'));
      return [];
    }

    // This is a container for all the charts below.
    $charts_container = [
      '#type' => 'container',
      'content' => [],
    ];

    /* Listing all the default chart types except 'gauge' and 'scatter'
     * (they come later).
     */
    $chart_types = ['area', 'bar', 'column', 'line', 'spline', 'pie', 'donut'];

    // Define a series to be used in multiple examples.
    $series = [
      '#type' => 'chart_data',
      '#title' => $this->t('5.0.x'),
      '#data' => [257, 235, 325, 340],
      '#color' => '#1d84c3',
    ];

    // Define an x-axis to be used in multiple examples.
    $xaxis = [
      '#type' => 'chart_xaxis',
      '#title' => $this->t('Months'),
      '#labels' => [
        $this->t('January 2021'),
        $this->t('February 2021'),
        $this->t('March 2021'),
        $this->t('April 2021'),
      ],
    ];

    // Define a y-axis to be used in multiple examples.
    $yaxis = [
      '#type' => 'chart_yaxis',
      '#title' => $this->t('Number of Installs'),
    ];

    // Iterate through the chart types and build a chart for each.
    foreach ($chart_types as $type) {
      $charts_container['content'][$type] = [
        '#type' => 'chart',
        '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
        '#title' => $this->t('@library @type Chart', [
          '@library' => ucfirst($library),
          '@type' => ucfirst($type),
        ]),
        '#chart_type' => $type,
        'series' => $series,
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#raw_options' => [],
        // e.g. ['chart' => ['backgroundColor' => '#000000']].
      ];
    }

    // Column chart with two series.
    $charts_container['content']['two_series_column'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Column Chart (Two Series)', ['@library' => ucfirst($library)]),
      '#chart_type' => 'column',
      'series_one' => $series,
      'series_two' => [
        '#type' => 'chart_data',
        '#title' => $this->t('8.x-3.x'),
        '#data' => [4330, 4413, 4212, 4431],
        '#color' => '#77b259',
      ],
      'x_axis' => $xaxis,
      'y_axis' => $yaxis,
      '#raw_options' => [],
    ];

    // Stacked Area Chart from a local CSV file.
    $file_contents = $this->getCsvContents();
    $charts_container['content']['from_csv_file'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Stacked Area Chart from CSV File', ['@library' => ucfirst($library)]),
      '#chart_type' => 'area',
      'series_seven_2' => [
        '#type' => 'chart_data',
        '#title' => $this->t('7.x-2.x'),
        // Using array_reverse because my file is desc rather than asc.
        '#data' => array_reverse($file_contents['7.x-2.x']),
        '#color' => '#76b7b2',
      ],
      'series_eight_three' => [
        '#type' => 'chart_data',
        '#title' => $this->t('8.x-3.x'),
        '#data' => array_reverse($file_contents['8.x-3.x']),
        '#color' => '#edc949',
      ],
      'series_five_zero' => [
        '#type' => 'chart_data',
        '#title' => $this->t('5.0.x'),
        '#data' => array_reverse($file_contents['5.0.x']),
        '#color' => '#ff9da7',
      ],
      'x_axis' => [
        '#type' => 'chart_xaxis',
        '#title' => $this->t('Week'),
        '#labels' => array_reverse($file_contents['Week']),
      ],
      'y_axis' => [
        '#type' => 'chart_yaxis',
        '#title' => $this->t('Number of Installs'),
      ],
      '#stacking' => TRUE,
    ];
    if ($library === 'chartjs') {
      // A real-life #raw_options use-case.
      $charts_container['content']['from_csv_file']['#raw_options'] = [
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
    elseif ($library === 'billboard' || $library === 'c3') {
      $charts_container['content']['from_csv_file']['#raw_options'] = [
        'axis' => [
          'x' => [
            'tick' => [
              'culling' => TRUE,
            ],
          ],
        ],
      ];
    }

    // Stacked column chart with two series.
    $charts_container['content']['stacked_two_series_column'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Stacked Column Chart (Two Series)', ['@library' => ucfirst($library)]),
      '#chart_type' => 'column',
      'series_one' => $series,
      'series_two' => [
        '#type' => 'chart_data',
        '#title' => $this->t('8.x-3.x'),
        '#data' => [4330, 4413, 4212, 4431],
        '#color' => '#77b259',
      ],
      'x_axis' => $xaxis,
      'y_axis' => $yaxis,
      '#stacking' => TRUE,
      '#raw_options' => [],
    ];

    // Combination chart (column and line).
    $charts_container['content']['combo'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Combination Chart', ['@library' => ucfirst($library)]),
      '#chart_type' => 'column',
      'series_one' => $series,
      'series_two' => [
        '#type' => 'chart_data',
        '#chart_type' => 'line',
        '#title' => $this->t('8.x-3.x'),
        '#data' => [4330, 4413, 4212, 4431],
        '#color' => '#77b259',
      ],
      'x_axis' => $xaxis,
      'y_axis' => $yaxis,
      '#raw_options' => [],
    ];

    // Combination chart (column and line) with secondary Y-Axis.
    $charts_container['content']['combo_dual_yaxes'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Combination Chart with Secondary Y-Axis', ['@library' => ucfirst($library)]),
      '#chart_type' => 'column',
      'series_one' => $series,
      'series_two' => [
        '#type' => 'chart_data',
        '#chart_type' => 'line',
        '#title' => $this->t('8.x-3.x'),
        '#data' => [4330, 4413, 4212, 4431],
        '#color' => '#77b259',
        '#target_axis' => 'y_axis_secondary',
      ],
      'x_axis' => $xaxis,
      'y_axis' => $yaxis,
      'y_axis_secondary' => [
        '#type' => 'chart_yaxis',
        '#title' => $this->t('Secondary Y-Axis'),
        '#opposite' => TRUE,
      ],
      '#raw_options' => [],
    ];

    // Radar chart. Not supported by C3.js or Google Charts (natively).
    if (!in_array($library, ['c3', 'google'])) {
      $charts_container['content']['radar'] = [
        '#type' => 'chart',
        '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
        '#title' => $this->t('@library Radar Chart', ['@library' => ucfirst($library)]),
        '#chart_type' => 'line',
        'series' => $series,
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#polar' => TRUE,
        '#raw_options' => [],
      ];
    }

    // Gauge chart. Not yet supported by Chart.js (as of 3.5.1).
    if ($library !== 'chartjs') {
      $charts_container['content']['gauge'] = [
        '#type' => 'chart',
        '#title' => $this->t('@library Gauge Chart', ['@library' => ucfirst($library)]),
        '#chart_type' => 'gauge',
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
        '#raw_options' => [],
      ];
    }

    // Scatter chart.
    $charts_container['content']['scatter'] = [
      '#type' => 'chart',
      '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
      '#title' => $this->t('@library Scatter Chart', ['@library' => ucfirst($library)]),
      '#data_markers' => TRUE,
      '#chart_type' => 'scatter',
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
      '#stacking' => TRUE,
      '#raw_options' => [],
    ];

    if ($library === 'highcharts') {
      $charts_container['content']['php_override'] = [
        '#chart_id' => 'exampleidphp',
        '#type' => 'chart',
        '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
        '#title' => $this->t('@library Chart, Overridden By PHP Hook', ['@library' => ucfirst($library)]),
        '#chart_type' => 'column',
        'series' => $series,
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#color_changer' => TRUE,
        '#raw_options' => [],
      ];

      $charts_container['content']['js_override'] = [
        '#id' => 'exampleidjs',
        '#chart_id' => 'exampleidjschart',
        '#type' => 'chart',
        '#tooltips' => $charts_settings->get('charts_default_settings.display.tooltips'),
        '#title' => $this->t('@library Chart, Overridden By JS Function', ['@library' => ucfirst($library)]),
        '#chart_type' => 'column',
        'series' => $series,
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#raw_options' => [],
      ];
    }

    return $charts_container;
  }

  /**
   * Returns the CSV contents in an array with data organized by column.
   *
   * @return array
   *   The array of rows.
   */
  private function getCsvContents() {
    $file_path = $this->moduleList->getPath('charts_api_example');
    $file_name = $file_path . '/fixtures/charts_api_example_file.csv';
    $handle = fopen($file_name, 'r');
    $all_rows = [];
    while ($row = fgetcsv($handle)) {
      $all_rows['Week'][] = $row[0];
      $all_rows['7.x-2.x'][] = (int) $row[4];
      $all_rows['8.x-3.x'][] = (int) $row[6];
      $all_rows['5.0.x'][] = (int) $row[8];
    }
    fclose($handle);

    return $all_rows;
  }

}
