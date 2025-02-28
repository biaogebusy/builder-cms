<?php

namespace Drupal\charts_google\Plugin\chart\Library;

use Drupal\charts\Element\Chart as ChartElement;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\charts\TypeManager;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define a concrete class for a Chart.
 *
 * @Chart(
 *   id = "google",
 *   name = @Translation("Google"),
 *   types = {
 *     "area",
 *     "bar",
 *     "bubble",
 *     "column",
 *     "donut",
 *     "gauge",
 *     "line",
 *     "pie",
 *     "scatter",
 *     "spline",
 *   },
 * )
 */
class Google extends ChartBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The chart type manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected $chartTypeManager;

  /**
   * Constructs a \Drupal\views\Plugin\Block\ViewsBlockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The element info manager.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, ElementInfoManagerInterface $element_info, TypeManager $chart_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->elementInfo = $element_info;
    $this->chartTypeManager = $chart_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('element_info'),
      $container->get('plugin.manager.charts_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    return [
      'use_material_design' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['placeholder'] = [
      '#title' => $this->t('Placeholder'),
      '#type' => 'fieldset',
      '#description' => $this->t(
        'This is a placeholder for Google-specific library options. If you would like to help build this out, please work from <a href="@issue_link">this issue</a>.', [
          '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046980')
            ->toString(),
        ]),
    ];
    $form['use_material_design'] = [
      '#title' => $this->t('Use Material Design'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['use_material_design'] ?? FALSE,
      '#description' => $this->t('Use Material Design for charts.'),
    ];

    return $form;
  }

  /**
   * Submit configurations.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['use_material_design'] = $values['use_material_design'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    $chart_definition = [];
    // Convert the chart renderable to a proper definition.
    $chart_definition['visualization'] = $this->chartsGoogleVisualizationType($element['#chart_type']);
    $chart_definition = $this->chartsGooglePopulateChartOptions($element, $chart_definition);
    $chart_definition = $this->chartsGooglePopulateChartAxes($element, $chart_definition);
    $chart_definition = $this->chartsGooglePopulateChartData($element, $chart_definition);

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('google-chart-render');
    }

    if (!empty($element['#height']) || !empty($element['#width'])) {
      $element['#attributes']['style'] = 'height:' . $element['#height'] . $element['#height_units'] . ';width:' . $element['#width'] . $element['#width_units'] . ';';
    }

    // Trim out empty options.
    ChartElement::trimArray($chart_definition['options']);

    $element['#attached']['library'][] = 'charts_google/google';
    $element['#attributes']['class'][] = 'charts-google';
    $element['#chart_definition'] = $chart_definition;

    // Setting global options.
    $element['#attached']['drupalSettings']['charts']['google']['global_options'] = [
      'useMaterialDesign' => !empty($this->configuration['use_material_design']) ? 'true' : 'false',
      'chartType' => $element['#chart_type'],
    ];

    return $element;
  }

  /**
   * Utility to convert a Drupal renderable type to a Google visualization type.
   */
  public function chartsGoogleVisualizationType($renderable_type) {
    $types = [
      'area' => 'AreaChart',
      'bar' => 'BarChart',
      'column' => 'ColumnChart',
      'line' => 'LineChart',
      'spline' => 'SplineChart',
      'pie' => 'PieChart',
      'donut' => 'DonutChart',
      'gauge' => 'Gauge',
      'scatter' => 'ScatterChart',
      'bubble' => 'BubbleChart',
      'geo' => 'GeoChart',
      'table' => 'TableChart',
    ];
    $this->moduleHandler->alter('charts_google_visualization_types', $types);
    return $types[$renderable_type] ?? FALSE;
  }

  /**
   * Utility to populate main chart options.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   The returned chart definition.
   */
  public function chartsGooglePopulateChartOptions(array $element, array $chart_definition) {
    if (!empty($this->configuration['use_material_design'])) {
      $chart_definition['options']['theme'] = 'material';
      $chart_definition['options']['chart']['title'] = $element['#title'] ?? NULL;
      $chart_definition['options']['chart']['subtitle'] = $element['#subtitle'] ?? NULL;
      if ($element['#chart_type'] === 'bar') {
        $chart_definition['options']['bars'] = 'horizontal';
      }
    }
    else {
      $title = $element['#title'] ?? NULL;
      if ($title && !empty($element['#subtitle'])) {
        $title .= ': ' . $element['#subtitle'];
      }
      $chart_definition['options']['title'] = $title;
    }
    $chart_definition['options']['titleTextStyle']['color'] = $element['#title_color'];
    $chart_definition['options']['titleTextStyle']['bold'] = $element['#title_font_weight'] === 'bold';
    $chart_definition['options']['titleTextStyle']['italic'] = $element['#title_font_style'] === 'italic';
    $chart_definition['options']['titleTextStyle']['fontSize'] = $element['#title_font_size'];
    $chart_definition['options']['titlePosition'] = $element['#title_position'];
    $chart_definition['options']['colors'] = $element['#colors'];
    $chart_definition['options']['fontName'] = $element['#font'];
    $chart_definition['options']['fontSize'] = $element['#font_size'];
    $chart_definition['options']['backgroundColor']['fill'] = $element['#background'];
    $chart_definition['options']['isStacked'] = (bool) $element['#stacking'];
    $chart_definition['options']['tooltip']['trigger'] = $element['#tooltips'] ? 'focus' : 'none';
    $chart_definition['options']['tooltip']['isHtml'] = (bool) $element['#tooltips_use_html'];
    $chart_definition['options']['pieSliceText'] = $element['#data_labels'] ? NULL : 'none';
    $chart_definition['options']['legend']['position'] = $element['#legend_position'] ?? 'none';
    $chart_definition['options']['legend']['alignment'] = 'center';
    $chart_definition['options']['interpolateNulls'] = TRUE;
    if ($element['#chart_type'] === 'gauge') {
      $chart_definition['options']['redFrom'] = $element['#gauge']['red_from'];
      $chart_definition['options']['redTo'] = $element['#gauge']['red_to'];
      $chart_definition['options']['yellowFrom'] = $element['#gauge']['yellow_from'];
      $chart_definition['options']['yellowTo'] = $element['#gauge']['yellow_to'];
      $chart_definition['options']['GreenFrom'] = $element['#gauge']['green_from'];
      $chart_definition['options']['greenTo'] = $element['#gauge']['green_to'];
      $chart_definition['options']['min'] = (int) $element['#gauge']['min'];
      $chart_definition['options']['max'] = (int) $element['#gauge']['max'];
    }
    if ($element['#chart_type'] === 'donut') {
      $chart_definition['options']['pieHole'] = 0.4;
    }
    if ($element['#chart_type'] === 'bubble') {
      $chart_definition['options']['bubble']['textStyle']['fontSize'] = 11;
    }
    // @todo Legend title (and thus these properties) not supported by Google.
    $chart_definition['options']['legend']['title'] = $element['#legend_title'];
    $chart_definition['options']['legend']['titleTextStyle']['bold'] = $element['#legend_title_font_weight'] === 'bold';
    $chart_definition['options']['legend']['titleTextStyle']['italic'] = $element['#legend_title_font_style'] === 'italic';
    $chart_definition['options']['legend']['titleTextStyle']['fontSize'] = $element['#legend_title_font_size'];

    $chart_definition['options']['legend']['textStyle']['bold'] = $element['#legend_font_weight'] === 'bold';
    $chart_definition['options']['legend']['textStyle']['italic'] = $element['#legend_font_style'] === 'italic';
    $chart_definition['options']['legend']['textStyle']['fontSize'] = $element['#legend_font_size'];
    // If your labels are truncated, you may need to add (try adjusting the %).
    // $chart_definition['options']['chartArea']['height'] = '50%'.
    $chart_definition['options']['animation']['duration'] = 10000;
    $chart_definition['options']['animation']['easing'] = 'out';

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeepArray([
        $chart_definition,
        $element['#raw_options'],
      ]);
    }

    return $chart_definition;
  }

  /**
   * Utility to populate chart axes.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  public function chartsGooglePopulateChartAxes(array $element, array $chart_definition) {
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_xaxis' || $element[$key]['#type'] === 'chart_yaxis') {
        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Populate the chart data.
        $axis = [];
        $axis['title'] = $element[$key]['#title'] ?? '';
        $axis['titleTextStyle']['color'] = $element[$key]['#title_color'];
        $axis['titleTextStyle']['bold'] = $element[$key]['#title_font_weight'] === 'bold';
        $axis['titleTextStyle']['italic'] = $element[$key]['#title_font_style'] === 'italic';
        $axis['titleTextStyle']['fontSize'] = $element[$key]['#title_font_size'];
        // In Google, the row column of data is used as labels.
        if ($element[$key]['#labels'] && $element[$key]['#type'] === 'chart_xaxis') {
          foreach ($element[$key]['#labels'] as $label_key => $label) {
            $chart_definition['data'][$label_key + 1][0] = $label;
          }
        }
        $axis['textStyle']['color'] = $element[$key]['#labels_color'];
        $axis['textStyle']['bold'] = $element[$key]['#labels_font_weight'] === 'bold';
        $axis['textStyle']['italic'] = $element[$key]['#labels_font_style'] === 'italic';
        $axis['textStyle']['fontSize'] = $element[$key]['#labels_font_size'];
        $axis['slantedText'] = !empty($element[$key]['#labels_rotation']) ? TRUE : NULL;
        $axis['slantedTextAngle'] = $element[$key]['#labels_rotation'];
        $axis['gridlines']['color'] = $element[$key]['#grid_line_color'];
        $axis['baselineColor'] = $element[$key]['#base_line_color'];
        $axis['minorGridlines']['color'] = $element[$key]['#minor_grid_line_color'];
        $axis['viewWindowMode'] = isset($element[$key]['#max']) ? 'explicit' : NULL;
        $axis['viewWindow']['max'] = !empty($element[$key]['#max']) ? (int) $element[$key]['#max'] : NULL;
        $axis['viewWindow']['min'] = !empty($element[$key]['#min']) ? (int) $element[$key]['#min'] : NULL;

        // Merge in axis raw options.
        if (!empty($element[$key]['#raw_options'])) {
          $axis = NestedArray::mergeDeepArray([
            $axis,
            $element[$key]['#raw_options'],
          ]);
        }

        // Multi-axis support only applies to the major axis in Google charts.
        $chart_type = $this->chartTypeManager->getDefinition($element['#chart_type']);
        $axis_index = $element[$key]['#opposite'] ? 1 : 0;
        if ($element[$key]['#type'] === 'chart_xaxis') {
          $axis_keys = !$chart_type['axis_inverted'] ? ['hAxis'] : [
            'vAxes',
            $axis_index,
          ];
        }
        else {
          $axis_keys = !$chart_type['axis_inverted'] ? ['vAxes', $axis_index] : ['hAxis'];
        }
        $axis_drilldown = &$chart_definition['options'];
        foreach ($axis_keys as $axis_key) {
          $axis_drilldown = &$axis_drilldown[$axis_key];
        }
        $axis_drilldown = $axis;
      }
    }

    return $chart_definition;
  }

  /**
   * Utility to populate chart data.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  public function chartsGooglePopulateChartData(array &$element, array $chart_definition) {
    $chart_definition['options']['series'] = [];
    $chart_type = $this->chartTypeManager->getDefinition($element['#chart_type']);
    $series_number = 0;
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        $series = [];

        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Convert target named axis keys to integers.
        $axis_index = 0;
        if (isset($element[$key]['#target_axis'])) {
          $axis_name = $element[$key]['#target_axis'];
          foreach (Element::children($element) as $axis_key) {
            $multi_axis_type = $chart_type['axis_inverted'] ? 'chart_xaxis' : 'chart_yaxis';
            if ($element[$axis_key]['#type'] === $multi_axis_type) {
              if ($axis_key === $axis_name) {
                break;
              }
              $axis_index++;
            }
          }
          $series['targetAxisIndex'] = $axis_index;
        }

        // Allow data to provide the labels. This will override the axis
        // settings.
        if ($element[$key]['#labels']) {
          foreach ($element[$key]['#labels'] as $label_index => $label) {
            $chart_definition['data'][$label_index + 1][0] = $label;
          }
        }
        if ($element[$key]['#title']) {
          $chart_definition['data'][0][$series_number + 1] = $element[$key]['#title'];
        }
        foreach ($element[$key]['#data'] as $index => $data_value) {
          // Nested array values typically used for scatter charts. This weird
          // approach leaves columns empty in order to make arbitrary pairings.
          // See https://developers.google.com/chart/interactive/docs/gallery/scatterchart#Data_Format
          if (is_array($data_value)) {
            if ($chart_type['id'] === 'scatter') {
              $chart_definition['data'][] = [
                0 => $data_value[0],
                $series_number + 1 => $data_value[1],
              ];
            }
            elseif ($chart_type['id'] === 'bubble') {
              $chart_definition['data'][] = [
                0 => $element[$key]['#title'],
                1 => $data_value[0],
                $series_number + 2 => $data_value[1],
                $series_number + 3 => $data_value[2],
              ];
            }
            else {
              if (!empty($data_value['color']) && (in_array($chart_type['id'], [
                'pie',
                'donut',
              ]))) {
                $chart_definition['options']['slices'][$index]['color'] = $data_value['color'];
                unset($data_value['color']);
                $data_value = array_values($data_value);
              }
              $chart_definition['data'][$index + 1] = $data_value;
            }
          }
          // Most charts provide a single-dimension array of values.
          else {
            if ($chart_type['id'] === 'gauge') {
              $data_value = [$element[$key]['#title'], $data_value];
              $chart_definition['data'][$index + 1] = $data_value;
            }
            else {
              $chart_definition['data'][$index + 1][$series_number + 1] = $data_value;
            }
          }
        }

        $series['color'] = $element[$key]['#color'];
        // Scatter charts are not visible if the data_markers are disabled.
        if ($chart_type['id'] === 'scatter') {
          $element['#data_markers'] = TRUE;
        }
        $series['pointSize'] = !empty($element['#data_markers']) ? 3 : 0;
        $series['visibleInLegend'] = $element[$key]['#show_in_legend'];

        // Labels only supported on pies.
        $series['pieSliceText'] = $element[$key]['#show_labels'] ? 'label' : 'none';

        // These properties are not real Google Charts properties. They are
        // utilized by the formatter in charts_google.js.
        $decimal_count = $element[$key]['#decimal_count'] ? '.' . str_repeat('0', $element[$key]['#decimal_count']) : '';
        $prefix = $this->chartsGoogleEscapeIcuCharacters($element[$key]['#prefix'] ?? '');
        $suffix = $this->chartsGoogleEscapeIcuCharacters($element[$key]['#suffix'] ?? '');
        $format = $prefix . '#' . $decimal_count . $suffix;
        $series['_format']['format'] = $format;

        // @todo Convert this from PHP's date format to ICU format.
        // See https://developers.google.com/chart/interactive/docs/reference#dateformatter.
        // $series['_format']['dateFormat'] = $element[$key]['#date_format'];
        // Conveniently only the axis that supports multiple axes is the one;
        // that can receive formatting, so we know that the key will;
        // always be plural.
        $axis_type = $chart_type['axis_inverted'] ? 'hAxes' : 'vAxes';
        $chart_definition['options'][$axis_type][$axis_index]['format'] = $format;

        // Convert to a ComboChart if mixing types.
        // See https://developers.google.com/chart/interactive/docs/gallery/combochart?hl=en.
        if ($element[$key]['#chart_type'] && !in_array($element[$key]['#chart_type'], ['bubble', 'scatter'])) {
          // Oddly Google calls a "column" chart a "bars" series.
          // Using actual bar.
          // charts is not supported in combo charts with Google.
          $main_chart_type = $element['#chart_type'] === 'column' ? 'bars' : $element['#chart_type'];
          $chart_definition['visualization'] = 'ComboChart';
          $chart_definition['options']['seriesType'] = $main_chart_type;

          $data_chart_type = $element[$key]['#chart_type'] === 'column' ? 'bars' : $element[$key]['#chart_type'];
          $series['type'] = $data_chart_type;
        }

        // Merge in point raw options.
        if (!empty($data_item['#raw_options'])) {
          $series = NestedArray::mergeDeepArray([
            $series,
            $data_item['#raw_options'],
          ]);
        }

        // Add the series to the main chart definition.
        ChartElement::trimArray($series);
        $chart_definition['options']['series'][$series_number] = $series;

        // Merge in any point-specific data points.
        foreach (Element::children($element[$key]) as $sub_key) {
          if ($element[$key][$sub_key]['#type'] === 'chart_data_item') {

            // Make sure defaults are loaded.
            if (empty($element[$key][$sub_key]['#defaults_loaded'])) {
              $element[$key][$sub_key] += $this->elementInfo->getInfo($element[$key][$sub_key]['#type']);
            }

            $data_item = $element[$key][$sub_key];
            if ($data_item['#data']) {
              $chart_definition['data'][$sub_key + 1][$series_number + 1] = $data_item['#data'];
            }
            // These data properties are manually applied to cells in JS.
            // Color role not yet supported. See https://code.google.com/p/google-visualization-api-issues/issues/detail?id=1267
            $chart_definition['_data'][$sub_key + 1][$series_number + 1]['color'] = $data_item['#color'];
            $chart_definition['_data'][$sub_key + 1][$series_number + 1]['tooltip'] = $data_item['#title'];

            // Merge in data point raw options.
            if (!empty($data_item['#raw_options'])) {
              $chart_definition['_data'][$sub_key + 1][$series_number + 1] = NestedArray::mergeDeepArray([
                $chart_definition['_data'][$sub_key + 1][$series_number + 1],
                $data_item['#raw_options'],
              ]);
            }

            ChartElement::trimArray($chart_definition['_data'][$sub_key + 1][$series_number + 1]);
          }
        }

        $series_number++;

      }
    }

    // Once complete, normalize the chart data to ensure a full 2D structure.
    $data = $chart_definition['data'];

    if (in_array($element['#chart_type'], ['pie', 'donut', 'scatter'])) {
      // Populate the 0th row with the same number of values as the 1st row.
      $data[0] = array_fill(0, count($data[1]), $element[$key]['#title'] ?? '');
    }
    elseif ($element['#chart_type'] === 'bubble') {
      $data[0] = [
        $element[$key]['#title'] ?? '',
        $chart_definition['options']['hAxis']['title'] ?? '',
        $chart_definition['options']['vAxes'][0]['title'] ?? '',
        $chart_definition['options']['zAxis']['title'] ?? '',
      ];
    }
    else {
      array_unshift($data[0], '');
    }
    // Ensure consistent column count.
    $column_count = count($data[0]);
    $new_data = [];
    foreach ($data as $row => $values) {
      for ($n = 0; $n < $column_count; $n++) {
        if (!empty(array_values($values))) {
          $temp = array_values($values);
          $new_data[$row][$n] = $temp[$n] ?? NULL;
        }
      }
      ksort($new_data[$row]);
    }
    ksort($new_data);

    $chart_definition['data'] = $new_data;

    return $chart_definition;
  }

  /**
   * Utility to escape special characters in ICU number formats.
   *
   * Google will use the ICU format to auto-adjust numbers based on special
   * characters that are used in the format. This function escapes these special
   * characters so they just show up as the character specified.
   *
   * The format string is a subset of the ICU pattern set. For instance,
   * {pattern:'#,###%'} will result in output values "1,000%", "750%", and "50%"
   * for values 10, 7.5, and 0.5.
   */
  public function chartsGoogleEscapeIcuCharacters($string) {
    return preg_replace('/([0-9@#\.\-,E\+;%\'\*])/', "'$1'", $string);
  }

}
