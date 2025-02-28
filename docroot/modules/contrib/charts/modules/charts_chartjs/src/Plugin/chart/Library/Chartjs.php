<?php

namespace Drupal\charts_chartjs\Plugin\chart\Library;

use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Define a concrete class for a Chart.
 *
 * @Chart(
 *   id = "chartjs",
 *   name = @Translation("Chart.js"),
 *   types = {
 *     "area",
 *     "bar",
 *     "bubble",
 *     "column",
 *     "donut",
 *     "line",
 *     "pie",
 *     "polarArea",
 *     "scatter",
 *     "spline",
 *   },
 * )
 */
class Chartjs extends ChartBase {

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
      '#default_value' => $xaxis_configuration['horizontal_axis_title_align'] ?? '',
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
      '#default_value' => $this->configuration['yaxis']['vertical_axis_title_align'] ?? '',
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
    $chart_definition = [];

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('chartjs-render');
    }

    if (!empty($element['#height']) || !empty($element['#width'])) {
      $element['#content_prefix']['manual_sizing'] = [
        '#type' => 'inline_template',
        '#template' => '<div data-chartjs-render-wrapper style="display:inline-block;height:' . $element['#height'] . $element['#height_units'] . ';width:' . $element['#width'] . $element['#width_units'] . ';">',
      ];
      $element['#content_suffix']['manual_sizing'] = [
        '#type' => 'inline_template',
        '#template' => '</div>',
      ];
    }

    $chart_definition = $this->populateOptions($element, $chart_definition);
    $chart_definition = $this->populateCategories($element, $chart_definition);
    $chart_definition = $this->populateDatasets($element, $chart_definition);
    $chart_definition = $this->populateAxes($element, $chart_definition);

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeepArray([
        $chart_definition,
        $element['#raw_options'],
      ]);
    }

    $element['#attached']['library'][] = 'charts_chartjs/chartjs';
    $element['#attributes']['class'][] = 'charts-chartjs';
    $element['#chart_definition'] = $chart_definition;

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
    $x_axis_key = 'xaxis';
    $y_axis_key = 'yaxis';
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis') {
        $x_axis_key = $child;
        $xaxis_configuration = $this->configuration[$x_axis_key] ?? [];
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
      }
      if ($type === 'chart_yaxis') {
        $y_axis_key = $child;
      }
    }

    // Build array of axes info.
    $axes_info = [
      'x' => [
        'element' => $element[$x_axis_key] ?? [],
        'config' => $this->configuration[$x_axis_key] ?? [],
      ],
      'y' => [
        'element' => $element[$y_axis_key] ?? [],
        'config' => $this->configuration[$y_axis_key] ?? [],
      ],
    ];

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
        $chart_definition['options']['scales']['y'] = [
          'ticks' => [
            'beginAtZero' => NULL,
            'maxRotation' => $axes_info['y']['element']['#labels_rotation'] ?? 0,
            'minRotation' => $axes_info['y']['element']['#labels_rotation'] ?? 0,
          ],
          'maxTicksLimit' => 11,
          'precision' => NULL,
          'stepSize' => NULL,
          'suggestedMax' => NULL,
          'suggestedMin' => NULL,
          'stacked' => $stacking,
        ];

        // Set configured values for each axis.
        foreach ($axes_info as $axis_id => $axis_info) {
          $axis_element = $axis_info['element'];
          $axis_config = $axis_info['config'];

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
    $chart_definition['options']['plugins']['legend'] = $this->buildLegend($element);

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
            return gettype($item) === 'array' ? array_values($item) : $item;
          }, $element[$child]['#data']);
        }
      }
      // Merge in axis raw options.
      if (!empty($element[$child]['#raw_options'])) {
        $categories = NestedArray::mergeDeepArray([
          $categories,
          $element[$child]['#raw_options'],
        ]);
      }
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
        $dataset = new \stdClass();
        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          if (isset($series_data[$data_index])) {
            $series_data[$data_index][] = $data;
          }
          else {
            if ($chart_type === 'scatter') {
              $data = ['y' => $data[1], 'x' => $data[0]];
            }
            if ($chart_type === 'bubble') {
              /*
               * The radius is not scaled in Chart.js, so it can look very bad.
               * For suggestions about how to deal with this, see:
               * https://github.com/chartjs/Chart.js/issues/3355
               */
              $data = ['y' => $data[1], 'x' => $data[0], 'r' => $data[2]];
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
          $dataset->yAxisID = $element[$key]['#target_axis'];
        }
        $dataset->label = $element[$key]['#title'];
        $dataset->data = $series_data;

        // Set the background and border color.
        if (!empty($element[$key]['#color'])) {
          if (!in_array($chart_type, $this->getPieStyleTypes())) {
            $dataset->borderColor = $element[$key]['#color'];
          }
          $dataset->backgroundColor = $element[$key]['#color'];
        }
        if (in_array($chart_type, $this->getPieStyleTypes()) && !empty($element['#colors'])) {
          $dataset->backgroundColor = $element['#colors'];
        }

        $series_type = isset($element[$key]['#chart_type']) ? $this->populateChartType($element[$key]) : $chart_type;
        $dataset->type = $series_type;
        if (!empty($element[$key]['#chart_type']) && $element[$key]['#chart_type'] === 'area') {
          $dataset->fill = 'origin';
          $dataset->backgroundColor = $this->getTranslucentColor($element[$key]['#color']);
        }
        elseif ($element['#chart_type'] === 'area') {
          $dataset->fill = 'origin';
          $dataset->backgroundColor = $this->getTranslucentColor($element[$key]['#color']);
        }
        else {
          $dataset->fill = FALSE;
        }

        // Merge in dataset raw options.
        if (!empty($element[$key]['#raw_options'])) {
          $dataset = NestedArray::mergeDeepArray([
            $dataset,
            $element[$key]['#raw_options'],
          ]);
        }

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
        // Gauge is currently not supported by Chart.js.
        $type = 'donut';
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
   * Returns pie-style chart types.
   *
   * @return array
   *   An array of pie-style chart types.
   */
  private static function getPieStyleTypes(): array {
    return [
      'pie',
      'doughnut',
      'polarArea',
    ];
  }

}
