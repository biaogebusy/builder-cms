<?php

namespace Drupal\charts_chartjs\Plugin\chart\Library;

use Drupal\charts\ApplyRawOptionsTrait;
use Drupal\charts\Attribute\Chart;
use Drupal\charts\BackgroundColorTrait;
use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The 'Chartjs' chart type attribute.
 */
#[Chart(
  id: "chartjs",
  name: new TranslatableMarkup("Chart.js"),
  types: [
    "area",
    "bar",
    "bubble",
    "column",
    "donut",
    "gauge",
    "line",
    "pie",
    "polarArea",
    "scatter",
    "spline",
    "treemap",
  ],
  example_route: "charts_chartjs_api_example.display",
)]
class Chartjs extends ChartBase implements ContainerFactoryPluginInterface {

  use ApplyRawOptionsTrait;
  use BackgroundColorTrait;

  /**
   * Constructs a \Drupal\views\Plugin\Block\ViewsBlockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, ?ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $form_builder);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'xaxis' => [
        'autoskip' => TRUE,
        'horizontal_axis_title_align' => 'start',
      ],
      'yaxis' => [
        'vertical_axis_title_align' => 'start',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['intro_text'] = [
      '#markup' => $this->t('<p>This is a placeholder for Chart.js-specific library options. If you would like to add more chartjs specific settings, please work from <a href="@issue_link">this issue</a>.</p>', [
        '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046984')->toString(),
      ]),
    ];

    $xaxis_configuration = $this->configuration['xaxis'] ?? [];
    $form['xaxis'] = [
      '#title' => $this->t('X-Axis Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];
    $form['xaxis']['autoskip'] = [
      '#title' => $this->t('Enable autoskip'),
      '#type' => 'checkbox',
      '#default_value' => !empty($xaxis_configuration['autoskip']),
    ];
    $form['xaxis']['horizontal_axis_title_align'] = [
      '#title' => $this->t('Align horizontal axis title'),
      '#type' => 'select',
      '#options' => [
        'start' => $this->t('Start'),
        'center' => $this->t('Center'),
        'end' => $this->t('End'),
      ],
      '#default_value' => $xaxis_configuration['horizontal_axis_title_align'] ?? 'center',
    ];
    $form['yaxis'] = [
      '#title' => $this->t('Y-Axis Settings'),
      '#type' => 'fieldset',
      '#tree' => TRUE,
    ];
    $form['yaxis']['vertical_axis_title_align'] = [
      '#title' => $this->t('Align vertical axis title'),
      '#type' => 'select',
      '#options' => [
        'start' => $this->t('Start'),
        'center' => $this->t('Center'),
        'end' => $this->t('End'),
      ],
      '#default_value' => $this->configuration['yaxis']['vertical_axis_title_align'] ?? 'center',
    ];

    return $form;
  }

  /**
   * Build configurations.
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
      $this->configuration['xaxis'] = $values['xaxis'];
      $this->configuration['yaxis'] = $values['yaxis'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('chartjs-render');
    }

    // Handle manual sizing wrapper.
    // Only add the wrapper if at least one dimension is specified.
    if (!empty($element['#height']) || !empty($element['#width'])) {
      $style = 'display:inline-block;';

      if (!empty($element['#height'])) {
        $style .= 'height:' . $element['#height'] . $element['#height_units'] . ';';
      }

      if (!empty($element['#width'])) {
        $style .= 'width:' . $element['#width'] . $element['#width_units'] . ';';
      }

      $element['#content_prefix']['manual_sizing'] = [
        '#type' => 'inline_template',
        '#template' => '<div data-chartjs-render-wrapper style="' . $style . '">',
      ];
      $element['#content_suffix']['manual_sizing'] = [
        '#type' => 'inline_template',
        '#template' => '</div>',
      ];
    }

    // Use raw definition if provided, otherwise build from elements.
    if (!empty($element['#chart_definition'])) {
      $chart_definition = $element['#chart_definition'];
    }
    else {
      $chart_definition = [];
      $chart_definition = $this->populateOptions($element, $chart_definition);
      $chart_definition = $this->populateCategories($element, $chart_definition);
      $chart_definition = $this->populateDatasets($element, $chart_definition);
      $chart_definition = $this->populateAxes($element, $chart_definition);
      if ($element['#chart_type'] === 'gauge') {
        $chart_definition = $this->buildGaugeChart($element, $chart_definition);
      }
    }

    // Workaround because Chart.js does not natively support background color.
    $element = $this->applyBackgroundColor($element);

    // Merge in chart raw options (applies to both methods).
    $chart_definition = $this->applyRawOptions($element, $chart_definition);

    $element['#attached']['library'][] = 'charts_chartjs/chartjs';
    if ($element['#chart_type'] === 'treemap') {
      $element['#attached']['library'][] = 'charts_chartjs/chartjs_treemap_plugin';
    }
    $element['#attributes']['class'][] = 'charts-chartjs';
    $element['#chart_definition'] = $chart_definition;

    // Attach the color changer form if requested.
    if (!empty($element['#color_changer']) && empty($element['#in_preview_mode'])) {
      $element = $this->applyColorChanger($element, $chart_definition);
    }

    return $element;
  }

  /**
   * Utility to apply color changer options.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   The chart element.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  private function applyColorChanger(array $element, array $chart_definition): array {
    $chart_type = $chart_definition['type'] ?? 'line';
    $datasets = $chart_definition['data']['datasets'] ?? [];

    // Transform datasets to be compatible with BaseColorChanger.
    $formatted_series = [];
    foreach ($datasets as $index => $dataset) {
      // Ensure the label is a string.
      $label = $dataset['label'] ?? '';
      if (is_array($label)) {
        // If it's an array, we grab the first value or use a fallback.
        $label = reset($label);
      }

      // Use a string fallback for the label.
      $series_name = !empty($label) ? $label : $this->t('Series @n', ['@n' => $index]);

      if (in_array($chart_type, ['pie', 'doughnut'])) {
        $formatted_series[$index] = [
          'name' => $series_name,
          'data' => [],
        ];

        foreach ($chart_definition['data']['labels'] as $i => $label_item) {
          // Ensure individual data point names are strings too.
          $item_name = is_array($label_item) ? reset($label_item) : $label_item;
          $formatted_series[$index]['data'][$i] = [
            'name' => $item_name,
            'color' => $dataset['backgroundColor'][$i] ?? '#000000',
          ];
        }
      }
      else {
        $formatted_series[$index] = [
          'name' => $series_name,
          'color' => $dataset['backgroundColor'] ?? '#000000',
        ];
      }
    }

    $form_state_items = [
      'chart_series' => $formatted_series,
      'chart_id' => $element['#id'],
      'chart_type' => $chart_type,
    ];

    $element['#attached']['library'][] = 'charts_chartjs/color_changer';
    $element['#content_suffix']['color_changer'] = $this->colorChangerFormBuilder($form_state_items);

    return $element;
  }

  /**
   * Populate chart axes.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateAxes(array $element, array $chart_definition) {
    $stacking = !empty($element['#stacking']) && $element['#stacking'] == 1;
    $chart_type = $chart_definition['type'];
    $children = Element::children($element);
    /*
     * Setting defaults based on what Views uses. However, API users may
     * have different keys for their X and Y axes.
     */
    $axes_info = [];
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis') {
        $x_axis_key = $child;
        $xaxis_configuration = $this->configuration['xaxis'] ?? [];
        if (!in_array($chart_type, $this->getPieStyleTypes())) {
          if ($chart_type !== 'radar') {
            $chart_definition['options']['scales']['x'] = [
              'stacked' => $stacking,
              'ticks' => [
                'autoSkip' => $xaxis_configuration['autoskip'] ?? 1,
                'maxRotation' => $element[$x_axis_key]['#labels_rotation'] ?? 0,
                'minRotation' => $element[$x_axis_key]['#labels_rotation'] ?? 0,
              ],
            ];
            if (!empty($element[$x_axis_key]['#title'])) {
              $chart_definition['options']['scales']['x']['title']['display'] = TRUE;
              $chart_definition['options']['scales']['x']['title']['text'] = $element[$x_axis_key]['#title'];
              $chart_definition['options']['scales']['x']['title']['align'] = $xaxis_configuration['horizontal_axis_title_align'] ?? '';
            }
          }
        }
        $axes_info['x'] = [
          'element' => $element[$x_axis_key] ?? [],
          'config' => $this->configuration['xaxis'] ?? [],
        ];
      }
      elseif ($type === 'chart_yaxis') {
        $target_axes = array_column($chart_definition['data']['datasets'], 'yAxisID');
        $y_axis_key = in_array($child, $target_axes) ? $child : 'y';
        $axes_info[$y_axis_key] = [
          'element' => $element[$child] ?? [],
          'config' => $this->configuration['yaxis'] ?? [],
        ];
      }
    }

    // Build axes options in chart_definition.
    if (!in_array($chart_type, $this->getPieStyleTypes())) {
      if (!empty($element['#stacking']) && $element['#stacking'] == 1) {
        $stacking = TRUE;
      }
      else {
        $stacking = FALSE;
      }
      if ($chart_type !== 'radar') {
        $chart_definition['options']['scales']['x'] = [
          'stacked' => $stacking,
          'ticks' => [
            'autoSkip' => $axes_info['x']['config']['autoskip'] ?? 1,
            'maxRotation' => $axes_info['x']['element']['#labels_rotation'] ?? 0,
            'minRotation' => $axes_info['x']['element']['#labels_rotation'] ?? 0,
          ],
        ];

        // Set configured values for each axis.
        foreach ($axes_info as $axis_id => $axis_info) {
          $axis_element = $axis_info['element'];
          $axis_config = $axis_info['config'];

          if ($axis_element['#type'] == 'chart_yaxis') {
            $chart_definition['options']['scales'][$axis_id] = [
              'ticks' => [
                'beginAtZero' => NULL,
                'maxRotation' => $axes_info[$axis_id]['element']['#labels_rotation'] ?? 0,
                'minRotation' => $axes_info[$axis_id]['element']['#labels_rotation'] ?? 0,
              ],
              'maxTicksLimit' => 11,
              'precision' => NULL,
              'stepSize' => NULL,
              'suggestedMax' => NULL,
              'suggestedMin' => NULL,
              'stacked' => $stacking,
            ];
          }

          // Set the axis type.
          if (!empty($axis_element['#axis_type'])) {
            $chart_definition['options']['scales'][$axis_id]['type'] = $axis_element['#axis_type'];
          }

          // Set axis position.
          if (!empty($axis_element['#opposite'])) {
            $chart_definition['options']['scales'][$axis_id]['position'] = 'right';
          }

          // Set min and max values.
          foreach (['min', 'max'] as $range_value) {
            if (isset($axis_element["#$range_value"])) {
              $chart_definition['options']['scales'][$axis_id][$range_value] = $axis_element["#$range_value"];
            }
          }

          // Set title properties.
          if (!empty($axis_element['#title'])) {
            $chart_definition['options']['scales'][$axis_id]['title']['display'] = TRUE;
            $chart_definition['options']['scales'][$axis_id]['title']['text'] = $axis_element['#title'];

            if (!empty($axis_config['vertical_axis_title_align'])) {
              $chart_definition['options']['scales'][$axis_id]['title']['align'] = $axis_config['vertical_axis_title_align'];
            }
          }

          // Set title color.
          if (!empty($axis_element['#title_color'])) {
            $chart_definition['options']['scales'][$axis_id]['title']['color'] = $axis_element['#title_color'];
          }

          // Set title font.
          foreach (['weight', 'style', 'size'] as $font_attribute) {
            $config_name = "#title_font_$font_attribute";
            if (!empty($axis_element[$config_name])) {
              $chart_definition['options']['scales'][$axis_id]['title']['font'][$font_attribute] = $axis_element[$config_name];
            }
          }

          // Set tick color.
          foreach (['color', 'rotation'] as $tick_attribute) {
            $config_name = "#labels_$tick_attribute";
            if (!empty($axis_element[$config_name])) {
              $chart_definition['options']['scales'][$axis_id]['ticks'][$tick_attribute] = $axis_element[$config_name];
            }
          }

          // Set tick font.
          foreach (['weight', 'style', 'size'] as $font_attribute) {
            $config_name = "#labels_font_$font_attribute";
            if (!empty($axis_element[$config_name])) {
              $chart_definition['options']['scales'][$axis_id]['ticks']['font'][$font_attribute] = $axis_element[$config_name];
            }
          }
          // Set grid line colors.
          if (!empty($axis_element['#grid_line_color'])) {
            $chart_definition['options']['scales'][$axis_id]['grid']['color'] = $axis_element['#grid_line_color'];
          }
          if (!empty($axis_element['#base_line_color'])) {
            $chart_definition['options']['scales'][$axis_id]['grid']['borderColor'] = $axis_element['#base_line_color'];
          }
        }
      }
    }

    return $chart_definition;
  }

  /**
   * Populate options.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateOptions(array $element, array $chart_definition) {
    $chart_type = $this->populateChartType($element);
    $chart_definition['type'] = $chart_type;

    // Horizontal bar charts are configured by changing the bar chart indexAxis.
    // See https://chartjs.org/docs/latest/charts/bar.html#horizontal-bar-chart.
    if ($element['#chart_type'] === 'bar') {
      $chart_definition['options']['indexAxis'] = 'y';
    }

    $chart_definition['options']['plugins']['title'] = $this->buildTitle($element);
    if (!empty($element['#subtitle'])) {
      $chart_definition['options']['plugins']['subtitle'] = [
        'display' => TRUE,
        'text' => $element['#subtitle'],
      ];
    }
    $chart_definition['options']['plugins']['tooltip']['enabled'] = $element['#tooltips'];
    if (!empty($element['#data_labels'])) {
      $chart_definition['options']['plugins']['datalabels'] = [];
    }
    $chart_definition['options']['plugins']['legend'] = $this->buildLegend($element);

    if (!empty($element['#connect_nulls'])) {
      $chart_definition['options']['spanGaps'] = TRUE;
    }

    return $chart_definition;
  }

  /**
   * Populate categories.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateCategories(array $element, array $chart_definition) {
    $children = Element::children($element);
    $categories = [];
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis' && isset($element[$child]['#labels'])) {
        if (in_array($element['#chart_type'], $this->getPieStyleTypes())) {
          $categories = $element[$child]['#labels'];
          break;
        }
        $categories = is_array($element[$child]['#labels']) ? array_map('strip_tags', $element[$child]['#labels']) : [];
      }
      if (in_array($element['#chart_type'], $this->getPieStyleTypes())
        && $type !== 'chart_xaxis') {
        if ($element[$child]['#type'] === 'chart_data') {
          // Get the first item in each array inside $element[$child]['#data'].
          $categories = array_map(function ($item) {
            if (!empty($item['color'])) {
              unset($item['color']);
            }
            return is_array($item) ? reset($item) : $item;
          }, $element[$child]['#data']);
        }
      }
      // Merge in axis raw options.
      $categories = $this->applyRawOptions($element[$child], $categories);
    }
    $chart_definition['data']['labels'] = $categories;
    return $chart_definition;
  }

  /**
   * Populate Dataset.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateDatasets(array $element, array $chart_definition) {
    $chart_type = $chart_definition['type'];
    $datasets = [];
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        $series_data = [];
        $dataset = [];
        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          if (isset($series_data[$data_index])) {
            $series_data[$data_index][] = $data;
          }
          else {
            if ($chart_type === 'scatter') {
              $data = [
                'x' => $data[0] ?? 0,
                'y' => $data[1] ?? 0,
              ];
            }
            if ($chart_type === 'bubble') {
              /*
               * The radius is not scaled in Chart.js, so it can look very bad.
               * For suggestions about how to deal with this, see:
               * https://github.com/chartjs/Chart.js/issues/3355
               */
              $data = [
                'x' => $data[0] ?? 0,
                'y' => $data[1] ?? 0,
                'r' => $data[2] ?? 0,
              ];
            }
            // Convert the array from Views when using pie-type charts
            // and no label field.
            if (in_array($chart_type, $this->getPieStyleTypes()) && !empty($data['color'])) {
              $element['#colors'][$data_index] = $data['color'];
              unset($data['color']);
              $data = array_values($data);
            }
            /*
             * This is here to account for differences between Views and
             * the API. Will change if someone can find a better way.
             */
            if (in_array($chart_type, $this->getPieStyleTypes()) && !empty($data[1])) {
              $data = $data[1];
            }
            $series_data[$data_index] = $data;
          }
        }
        if (!empty($element[$key]['#target_axis'])) {
          $dataset['yAxisID'] = $element[$key]['#target_axis'];
        }
        $dataset['label'] = $element[$key]['#title'];
        $dataset['data'] = $series_data;

        // Set the background and border color.
        if (!empty($element[$key]['#color'])) {
          if (!in_array($chart_type, $this->getPieStyleTypes())) {
            $dataset['borderColor'] = $element[$key]['#color'];
          }
          $dataset['backgroundColor'] = $element[$key]['#color'];
        }
        if (in_array($chart_type, $this->getPieStyleTypes()) && !empty($element['#colors'])) {
          $dataset['backgroundColor'] = $element['#colors'];
        }

        $series_type = isset($element[$key]['#chart_type']) ? $this->populateChartType($element[$key]) : $chart_type;
        $dataset['type'] = $series_type;
        if (!empty($element[$key]['#chart_type']) && $element[$key]['#chart_type'] === 'area') {
          $dataset['fill'] = 'origin';
          $dataset['backgroundColor'] = $this->getTranslucentColor($element[$key]['#color']);
        }
        elseif ($element['#chart_type'] === 'area') {
          $dataset['fill'] = 'origin';
          $dataset['backgroundColor'] = $this->getTranslucentColor($element[$key]['#color']);
        }
        else {
          $dataset['fill'] = FALSE;
        }

        // Merge in dataset raw options.
        $dataset = $this->applyRawOptions($element[$key], $dataset);

        $datasets[] = $dataset;
      }

    }

    $chart_definition['data']['datasets'] = $datasets;

    return $chart_definition;
  }

  /**
   * Outputs a type that can be used by Chart.js.
   *
   * @param array $element
   *   The given element.
   *
   * @return string
   *   The generated type.
   */
  protected function populateChartType(array $element) {
    switch ($element['#chart_type']) {
      case 'bar':

      case 'column':
        $type = 'bar';
        break;

      case 'area':

      case 'spline':
        $type = 'line';
        break;

      case 'donut':
        $type = 'doughnut';
        break;

      case 'gauge':
        // Chart.js does not have a native gauge type. Gauges are rendered as
        // a half-doughnut combined with a "doughnutLabel" annotation provided
        // by the chartjs-plugin-annotation library.
        // @see https://www.chartjs.org/chartjs-plugin-annotation/3.1.0/samples/doughnutLabel/gauge.html
        $type = 'doughnut';
        break;

      default:
        $type = $element['#chart_type'];
        break;
    }
    if (isset($element['#polar']) && $element['#polar'] == 1) {
      if ($element['#chart_type'] === 'area' || $element['#chart_type'] === 'polarArea') {
        $type = 'polarArea';
      }
      else {
        $type = 'radar';
      }
    }

    return $type;
  }

  /**
   * Builds legend based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The legend array.
   */
  protected function buildLegend(array $element) {
    $legend = [];
    // Configure the legend display.
    $legend['display'] = (bool) $element['#legend'];

    // Configure legend position.
    if (!empty($element['#legend_position'])) {
      $legend['position'] = $element['#legend_position'];
      if (!empty($element['#legend_font_weight'])) {
        $legend['labels']['font']['weight'] = $element['#legend_font_weight'];
      }
      if (!empty($element['#legend_font_style'])) {
        $legend['labels']['font']['style'] = $element['#legend_font_style'];
      }
      if (!empty($element['#legend_font_size'])) {
        $legend['labels']['font']['size'] = $element['#legend_font_size'];
      }
    }

    return $legend;
  }

  /**
   * Builds title based on element properties.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The title array.
   */
  protected function buildTitle(array $element) {
    $title = [];
    if (!empty($element['#title'])) {
      $title = [
        'display' => TRUE,
        'text' => $element['#title'],
      ];
      if (!empty($element['#title_position'])) {
        if (in_array($element['#title_position'], ['in', 'out'])) {
          $title['position'] = 'top';
        }
        else {
          $title['position'] = $element['#title_position'];
        }
      }
      if (!empty($element['#title_color'])) {
        $title['color'] = $element['#title_color'];
      }
      if (!empty($element['#title_font_weight'])) {
        $title['font']['weight'] = $element['#title_font_weight'];
      }
      if (!empty($element['#title_font_style'])) {
        $title['font']['style'] = $element['#title_font_style'];
      }
      if (!empty($element['#title_font_size'])) {
        $title['font']['size'] = $element['#title_font_size'];
      }
    }

    return $title;
  }

  /**
   * Get translucent color.
   *
   * @param string $color
   *   The color.
   *
   * @return string
   *   The color.
   */
  protected function getTranslucentColor($color) {
    if (!$color) {
      return '';
    }
    $rgb = Color::hexToRgb($color);

    return 'rgba(' . implode(",", $rgb) . ',' . 0.5 . ')';
  }

  /**
   * Builds a gauge chart definition.
   *
   * Chart.js has no native gauge type, so the gauge is emulated with a
   * half-doughnut chart: the first slice represents the value and the second
   * (gray) slice represents the remainder of the range. The current value and
   * the series label are printed in the center of the gauge using the
   * "doughnutLabel" annotation from the chartjs-plugin-annotation library.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   *
   * @see https://www.chartjs.org/chartjs-plugin-annotation/3.1.0/samples/doughnutLabel/gauge.html
   */
  private function buildGaugeChart(array $element, array $chart_definition) {
    $gauge_settings = array_filter($element['#gauge'] ?? [], fn ($value) => $value !== '' && $value !== NULL);
    $gauge_settings += [
      'min' => 0,
      'max' => 100,
      'red_from' => 0,
      'red_to' => 50,
      'yellow_from' => 50,
      'yellow_to' => 85,
      'green_from' => 85,
      'green_to' => 100,
    ];
    $min = (float) $gauge_settings['min'];
    $max = (float) $gauge_settings['max'];

    // Extract the gauge value and label from the first dataset built by
    // populateDatasets().
    $dataset = $chart_definition['data']['datasets'][0] ?? [];
    $value = $dataset['data'][0] ?? 0;
    // Views/API data may come as [label, value] pairs.
    if (is_array($value)) {
      $value = end($value);
    }
    $value = (float) $value;
    $label = $dataset['label'] ?? '';
    if (is_array($label)) {
      $label = reset($label);
    }
    $label = (string) $label;

    // Keep the value inside the configured range.
    $value = max($min, min($max, $value));
    $color = $this->getGaugeColor($value, $gauge_settings);

    // A single dataset with two slices: the value and the remainder.
    $chart_definition['data']['labels'] = [$label];
    $chart_definition['data']['datasets'] = [
      [
        'label' => $label,
        'data' => [$value - $min, $max - $value],
        'backgroundColor' => [$color, 'rgb(234, 234, 234)'],
        'borderWidth' => 0,
        'type' => 'doughnut',
      ],
    ];

    // Render the doughnut as a half circle.
    $chart_definition['options']['aspectRatio'] = 2;
    $chart_definition['options']['circumference'] = 180;
    $chart_definition['options']['rotation'] = -90;

    // Print the value and the series label in the center of the gauge with
    // the doughnutLabel annotation type.
    $formatted_value = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $chart_definition['options']['plugins']['annotation']['annotations']['gaugeLabel'] = [
      'type' => 'doughnutLabel',
      'drawTime' => 'beforeDraw',
      'position' => ['y' => '-50%'],
      'content' => array_values(array_filter([$formatted_value, $label], fn ($item) => $item !== '')),
      'font' => [
        ['size' => 50, 'weight' => 'bold'],
        ['size' => 20],
      ],
      'color' => [$color, 'gray'],
    ];

    return $chart_definition;
  }

  /**
   * Resolves the gauge color for a given value.
   *
   * @param float $value
   *   The gauge value.
   * @param array $gauge_settings
   *   The gauge settings, containing "(red|yellow|green)_(from|to)" ranges.
   *
   * @return string
   *   A CSS color string.
   */
  private function getGaugeColor(float $value, array $gauge_settings) {
    $bands = [
      'red' => 'rgb(231, 24, 49)',
      'yellow' => 'rgb(239, 198, 0)',
      'green' => 'rgb(140, 214, 16)',
    ];
    foreach ($bands as $band => $color) {
      if (!isset($gauge_settings[$band . '_from']) || !isset($gauge_settings[$band . '_to'])) {
        continue;
      }
      if ($value >= (float) $gauge_settings[$band . '_from'] && $value <= (float) $gauge_settings[$band . '_to']) {
        return $color;
      }
    }
    // Fall back to gray when the value is not inside any configured band.
    return 'rgb(128, 128, 128)';
  }

  /**
   * Returns pie-style chart types.
   *
   * @return array
   *   An array of pie-style chart types.
   */
  private static function getPieStyleTypes(): array {
    return [
      'pie',
      'doughnut',
      'donut',
      'gauge',
      'polarArea',
    ];
  }

}
