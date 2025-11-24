<?php

namespace Drupal\charts_billboard\Plugin\chart\Library;

use Drupal\charts\Attribute\Chart;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The 'Billboard' chart type attribute.
 */
#[Chart(
  id: "billboard",
  name: new TranslatableMarkup("Billboard.js"),
  types: [
    "area",
    "arearange",
    "bar",
    "bubble",
    "candlestick",
    "column",
    "donut",
    "gauge",
    "line",
    "pie",
    "scatter",
    "spline",
  ]
)]
class Billboard extends ChartBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElementInfoManagerInterface $element_info, ?ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler);
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
      $container->get('element_info'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configurations = [
      'monochrome_pie' => FALSE,
    ] + parent::defaultConfiguration();

    return $configurations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['intro_text'] = [
      '#markup' => $this->t('This is a placeholder for Billboard.js-specific library options. If you would like to help build this out, please work from <a href="@issue_link">this issue</a>.', [
        '@issue_link' => Url::fromUri('https://www.drupal.org/project/charts/issues/3046983')->toString(),
      ]),
    ];
    $form['monochrome_pie'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Monochrome Pie/Donut Charts'),
      '#description' => $this->t('Previous iterations of this module had pie and donut charts with the same color for all the slices. Check this box if you wish to continue using just one color.'),
      '#default_value' => !empty($this->configuration['monochrome_pie']),
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
      $this->configuration['monochrome_pie'] = $values['monochrome_pie'];
    }
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
      $element['#id'] = Html::getUniqueId('chart-billboard');
    }
    $chart_definition['bindto'] = '#' . $element['#id'];

    $element['#attached']['library'][] = 'charts_billboard/billboard';
    $element['#attributes']['class'][] = 'charts-billboard charts-bb';
    $element['#chart_definition'] = $chart_definition;

    return $element;
  }

  /**
   * Get the chart type.
   *
   * @param string $chart_type
   *   The chart type.
   * @param bool $is_polar
   *   Whether the polar is checked.
   *
   * @return string
   *   The chart type.
   */
  protected function getType($chart_type, $is_polar = FALSE) {
    // If Polar is checked, then convert to Radar chart type.
    if ($is_polar) {
      $type = 'radar';
    }
    elseif ($chart_type === 'arearange') {
      $type = 'area-line-range';
    }
    else {
      $type = $chart_type == 'column' ? 'bar' : $chart_type;
    }
    return $type;
  }

  /**
   * Get options.
   *
   * @param string $type
   *   The chart type.
   * @param array $element
   *   The element.
   *
   * @return array
   *   The returned options.
   */
  protected function getOptionsByType($type, array $element) {
    $options = $this->getOptionsByCustomProperty($element, $type);
    if ($type === 'bar') {
      $options['width'] = $element['#width'];
    }

    return $options;
  }

  /**
   * Get the options by custom property.
   *
   * @param array $element
   *   The element.
   * @param string $type
   *   The chart type.
   *
   * @return array
   *   The return options.
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
    $type = $this->getType($element['#chart_type'], $element['#polar'] ?? FALSE);
    $title = $element['#title'] ?? '';
    if (!empty($element['#subtitle'])) {
      $title .= '\n' . $element['#subtitle'];
    }
    $chart_definition['title']['text'] = $title;
    $chart_definition['legend']['show'] = !empty($element['#legend_position']);
    if (!in_array($type, ['scatter', 'bubble'])) {
      $chart_definition['axis']['x']['type'] = 'category';
    }
    $chart_definition['data']['labels'] = (bool) $element['#data_labels'];

    if ($type === 'pie' || $type === 'donut') {
      // Do nothing.
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
    elseif (in_array($type, ['line', 'spline', 'step', 'area', 'area-spline'])) {
      $chart_definition['point']['show'] = !empty($element['#data_markers']);
      $chart_definition['line']['connectNull'] = !empty($element['#connect_nulls']);
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
   *   Return the chart definition.
   */
  private function populateAxes(array $element, array $chart_definition) {
    $children = Element::children($element);
    foreach ($children as $child) {
      $type = $element[$child]['#type'];
      if ($type === 'chart_xaxis') {
        $chart_definition['axis']['x']['label'] = $element[$child]['#title'] ?? '';
        $chart_type = $this->getType($element['#chart_type']);
        $categories = !empty($element[$child]['#labels']) ? $this->stripLabelTags($element[$child]['#labels']) : [];
        if (empty($categories)) {
          // If no labels are provided, fill the categories with empty values.
          $categories = $this->fillCategoriesWithoutLabels($chart_definition);
        }
        if (!in_array($chart_type, ['pie', 'donut'])) {
          if ($chart_type === 'scatter' || $chart_type === 'bubble') {
            // Do nothing.
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
    $type = $this->getType($element['#chart_type'], $element['#polar'] ?? FALSE);
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
        $types[$series_title] = $child_element['#chart_type'] ? $this->getType($child_element['#chart_type'], $element['#polar'] ?? FALSE) : $type;
        if (!in_array($type, ['scatter', 'bubble'])) {
          $columns[$columns_key_start][] = $series_title;
          foreach ($child_element['#data'] as $datum) {
            if (gettype($datum) === 'array') {
              if ($type === 'gauge') {
                array_shift($datum);
              }
              $columns[$columns_key_start][] = array_map(function ($item) {
                return isset($item) ? (float) strip_tags($item) : NULL;
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
          if (!empty($datum[0])) {
            // Remove any HTML for use in SVG text elements.
            // E.g. "<h2>This &amp; that</h2>" -> "This & that".
            $datum[0] = strip_tags(htmlspecialchars_decode($datum[0]));
          }
          $columns[] = $datum;
        }

        // Add colors for each segment.
        if (!empty($element['#colors']) && empty($this->configuration['monochrome_pie'])) {
          foreach ($element['#colors'] as $key => $color) {
            $chart_definition['color']['pattern'][$key] = $color;
          }
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

  /**
   * Create an array for when labels are empty.
   *
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   An empty array the length of the longest series.
   */
  private function fillCategoriesWithoutLabels(array $chart_definition): array {
    $columns = $chart_definition['data']['columns'];
    $max_items = 0;
    foreach ($columns as $column) {
      // Skip empty series or the x-axis series.
      if (empty($column) || $column[0] === 'x') {
        continue;
      }
      // Count items in the series (subtract 1 for the series name).
      $items_count = count($column) - 1;

      // Update max if this series has more items.
      if ($items_count > $max_items) {
        $max_items = $items_count;
      }
    }

    return array_fill(0, $max_items, []);
  }

}
