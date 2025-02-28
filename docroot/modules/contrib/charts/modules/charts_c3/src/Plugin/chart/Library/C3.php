<?php

namespace Drupal\charts_c3\Plugin\chart\Library;

use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
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
 *   id = "c3",
 *   name = @Translation("C3"),
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
class C3 extends ChartBase implements ContainerFactoryPluginInterface {

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * Constructs a \Drupal\views\Plugin\Block\ViewsBlockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElementInfoManagerInterface $element_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->elementInfo = $element_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('element_info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['intro_text'] = [
      '#markup' => $this->t('<p>This is a placeholder for C3.js-specific library options. If you would like to help build this out, please work from <a href="@issue_link">this issue</a>.</p>', [
        '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046982')->toString(),
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    // Populate chart settings.
    $chart_definition = [];

    $chart_definition = $this->populateOptions($element, $chart_definition);
    $chart_definition = $this->populateData($element, $chart_definition);
    $chart_definition = $this->populateAxes($element, $chart_definition);

    if (!empty($element['#height']) || !empty($element['#width'])) {
      $element['#attributes']['style'] = 'height:' . $element['#height'] . $element['#height_units'] . ';width:' . $element['#width'] . $element['#width_units'] . ';';
    }

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('chart-c3');
    }
    $chart_definition['bindto'] = '#' . $element['#id'];

    $element['#attached']['library'][] = 'charts_c3/c3';
    $element['#attributes']['class'][] = 'charts-c3';
    $element['#chart_definition'] = $chart_definition;

    return $element;
  }

  /**
   * The chart type.
   *
   * @param string $chart_type
   *   The chart type.
   * @param bool $is_polar
   *   Whether polar is checked.
   *
   * @return string
   *   Return the type.
   */
  protected function getType($chart_type, $is_polar = FALSE) {
    // If Polar is checked, then convert to Radar chart type.
    if ($is_polar) {
      $type = 'radar';
    }
    else {
      $type = $chart_type == 'column' ? 'bar' : $chart_type;
    }
    return $type;
  }

  /**
   * Get options by type.
   *
   * @param string $type
   *   The chart type.
   * @param array $element
   *   The element.
   *
   * @return array
   *   Return options.
   */
  protected function getOptionsByType($type, array $element) {
    $options = $this->getOptionsByCustomProperty($element, $type);
    if ($type === 'bar') {
      $options['width'] = $element['#width'];
    }

    return $options;
  }

  /**
   * Get options by custom property.
   *
   * @param array $element
   *   The element.
   * @param string $type
   *   The chart type.
   *
   * @return array
   *   Return options.
   */
  protected function getOptionsByCustomProperty(array $element, $type) {
    $options = [];
    $properties = Element::properties($element);
    // Remove properties which are not related to this chart type.
    $properties = array_filter($properties, function ($property) use ($type) {
      $query = '#chart_' . $type . '_';
      return substr($property, 0, strlen($query)) === $query;
    });
    foreach ($properties as $property) {
      $query = '#chart_' . $type . '_';
      $option_key = substr($property, strlen($query), strlen($property));
      $options[$option_key] = $element[$property];
    }
    return $options;
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
    $type = $this->getType($element['#chart_type']);
    $title = $element['#title'] ?? '';
    if (!empty($element['#subtitle'])) {
      $title .= ': ' . $element['#subtitle'];
    }
    $chart_definition['title']['text'] = $title;
    $chart_definition['legend']['show'] = !empty($element['#legend_position']);
    if (!in_array($type, ['scatter', 'bubble'])) {
      $chart_definition['axis']['x']['type'] = 'category';
    }
    $chart_definition['data']['labels'] = (bool) $element['#data_labels'];

    if ($type === 'pie' || $type === 'donut') {

    }
    elseif ($type === 'gauge') {
      $chart_definition['gauge']['min'] = $element['#gauge']['min'];
      $chart_definition['gauge']['max'] = $element['#gauge']['max'];
      $chart_definition['color']['pattern'] = [
        'red',
        'yellow',
        'green',
      ];
      $chart_definition['color']['threshold']['values'] = [
        $element['#gauge']['red_from'],
        $element['#gauge']['yellow_from'],
        $element['#gauge']['green_from'],
      ];
    }
    elseif ($type === 'line' || $type === 'spline') {
      $chart_definition['point']['show'] = !empty($element['#data_markers']);
    }
    else {
      /*
       * Billboard does not use bar, so column must be used. Since 'column'
       * is changed
       * to 'bar' in getType(), we need to use the value from the element.
       */
      if ($element['#chart_type'] === 'bar') {
        $chart_definition['axis']['rotated'] = TRUE;
      }
      elseif ($element['#chart_type'] === 'column') {
        $type = 'bar';
        $chart_definition['axis']['rotated'] = FALSE;
      }
    }
    $chart_definition['data']['type'] = $type;
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
   * Populate axes.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return chart definition.
   */
  private function populateAxes(array $element, array $chart_definition) {
    $children = Element::children($element);
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis') {
        $chart_definition['axis']['x']['label'] = $element[$child]['#title'] ?? '';
        $chart_type = $this->getType($element['#chart_type']);
        $categories = $this->stripLabelTags($element[$child]['#labels']);
        if (!in_array($chart_type, ['pie', 'donut'])) {
          if ($chart_type === 'scatter' || $chart_type === 'bubble') {
            // Scatter is not supported: https://github.com/c3js/c3/issues/1902
          }
          else {
            $chart_definition['data']['columns'][] = ['x'];
            $chart_definition['data']['x'] = 'x';
            $categories_keys = array_keys($chart_definition['data']['columns']);
            $categories_key = end($categories_keys);
            foreach ($categories as $category) {
              $chart_definition['data']['columns'][$categories_key][] = $category;
            }
          }
        }
        else {
          $chart_definition['data']['columns'] = array_map(NULL, $categories, $chart_definition['data']['columns']);
        }
      }
      if ($type === 'chart_yaxis') {
        if (!empty($element[$child]['#opposite']) && $element[$child]['#opposite'] === TRUE) {
          $chart_definition['axis']['y2']['show'] = TRUE;
          $this->setLabelMinMax($chart_definition, 'y2', $element[$child]);
        }
        else {
          $this->setLabelMinMax($chart_definition, 'y', $element[$child]);
        }
      }
    }

    return $chart_definition;
  }

  /**
   * Set the label, min, and max.
   *
   * @param array $chart_definition
   *   The chart definition.
   * @param string $axis
   *   The axis.
   * @param array $element
   *   The element.
   */
  private function setLabelMinMax(array &$chart_definition, string $axis, array $element): void {
    $chart_definition['axis'][$axis]['label'] = $element['#title'] ?? '';
    if (!empty($element['#min'])) {
      $chart_definition['axis'][$axis]['min'] = $element['#min'];
    }
    if (!empty($element['#max'])) {
      $chart_definition['axis'][$axis]['max'] = $element['#max'];
    }
  }

  /**
   * Populate data.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  private function populateData(array &$element, array $chart_definition) {
    $type = $this->getType($element['#chart_type']);
    $types = [];
    $children = Element::children($element);
    $y_axes = [];
    foreach ($children as $child) {
      $element_type = $element[$child]['#type'];
      if ($element_type === 'chart_yaxis') {
        $y_axes[] = $child;
      }
    }
    $data_elements = array_filter($children, function ($child) use ($element) {
      return $element[$child]['#type'] === 'chart_data';
    });

    $columns = $chart_definition['data']['columns'] ?? [];
    $column_keys = array_keys($columns);
    $columns_key_start = $columns ? end($column_keys) + 1 : 0;
    foreach ($data_elements as $key) {
      $child_element = $element[$key];
      // Make sure defaults are loaded.
      if (empty($child_element['#defaults_loaded'])) {
        $child_element += $this->elementInfo->getInfo($child_element['#type']);
      }
      if ($child_element['#color'] && $type !== 'gauge') {
        $chart_definition['color']['pattern'][] = $child_element['#color'];
      }
      if (!in_array($type, ['pie', 'donut'])) {
        $series_title = isset($child_element['#title']) ? strip_tags($child_element['#title']) : '';
        $types[$series_title] = $child_element['#chart_type'] ? $this->getType($child_element['#chart_type']) : $type;
        if (!in_array($type, ['scatter', 'bubble'])) {
          $columns[$columns_key_start][] = $series_title;
          foreach ($child_element['#data'] as $datum) {
            if (gettype($datum) === 'array') {
              if ($type === 'gauge') {
                array_shift($datum);
              }
              $columns[$columns_key_start][] = array_map(function ($item) {
                return isset($item) ? strip_tags($item) : NULL;
              }, $datum);
            }
            else {
              $columns[$columns_key_start][] = isset($datum) ? strip_tags($datum) : NULL;
            }
          }
        }
        else {
          $row = [];
          $row[$series_title][0] = $series_title;
          $row[$series_title . '_x'][0] = $series_title . '_x';
          foreach ($child_element['#data'] as $datum) {
            $row[$series_title][] = $datum[0];
            $row[$series_title . '_x'][] = $datum[1];
          }
          $chart_definition['data']['xs'][$series_title] = $series_title . '_x';
          foreach ($row as $value) {
            $columns[] = $value;
          }
          $columns = array_values($columns);
        }
      }
      else {
        foreach ($child_element['#data'] as $datum_index => $datum) {
          if (!empty($datum['color'])) {
            $chart_definition['color']['pattern'][$datum_index] = $datum['color'];
            unset($datum['color']);
            $datum = array_values($datum);
          }
          $columns[] = $datum;
        }

        // Add colors for each segment.
        foreach ($child_element['#grouping_colors'] ?? [] as $key => $colors_array) {
          $color = reset($colors_array);
          $chart_definition['color']['pattern'][$key] = $color;
        }
      }

      $columns_key_start++;
    }
    if ($element['#stacking']) {
      $chart_definition['data']['groups'] = [array_keys($types)];
    }
    $chart_definition['data']['types'] = $types;
    $chart_definition['data']['columns'] = $columns;

    if (count($y_axes) >= 2) {
      foreach ($columns as $index => $column) {
        if ($index <= 1) {
          $axis = ($index + 1) === 2 ? 2 : '';
          $chart_definition['data']['axes'][$column[0]] = 'y' . $axis;
        }
      }
    }

    return $chart_definition;
  }

  /**
   * Strip tags from each item in an array.
   *
   * @param array $items
   *   The array.
   *
   * @return array
   *   Return the cleaned array.
   */
  private function stripLabelTags(array $items): array {
    if (empty($items)) {
      return [];
    }
    $categories = [];
    foreach ($items as $item) {
      $categories[] = isset($item) ? strip_tags($item) : NULL;
    }

    return $categories;
  }

}
