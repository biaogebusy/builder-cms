<?php

namespace Drupal\charts_test\Plugin\chart\Library;

use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define a library to use for testing purposes.
 *
 * @Chart(
 *   id = "charts_test_library",
 *   name = @Translation("Charts Test Library"),
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
 *   },
 * )
 */
class ChartsTestLibrary extends ChartBase implements ContainerFactoryPluginInterface {

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
  public function defaultConfiguration() {
    return [
      'foo' => 'bar',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    // Populate chart settings.
    $chart_definition = [];

    $chart_definition = $this->populateOptions($element, $chart_definition);
    $chart_definition = $this->populateData($element, $chart_definition);

    if (!empty($element['#height']) || !empty($element['#width'])) {
      $element['#attributes']['style'] = 'height:' . $element['#height'] . $element['#height_units'] . ';width:' . $element['#width'] . $element['#width_units'] . ';';
    }

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('charts-charts-test-library-chart');
    }

    $element['#attributes']['class'][] = 'charts-charts-test-library-chart-container';
    $element['#chart_definition'] = $chart_definition;

    return $element;
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
    $chart_definition['title']['text'] = $element['#title'];
    $chart_definition['title']['color'] = $element['#title_color'];
    $chart_definition['title']['position'] = $element['#title_position'];
    $chart_definition['subtitle']['text'] = $element['#subtitle'];
    $chart_definition['type'] = $element['#chart_type'];
    $chart_definition['title']['font'] = [
      'weight' => $element['#title_font_weight'],
      'style' => $element['#title_font_style'],
      'size' => $element['#title_font_size'],
    ];
    $chart_definition['colors'] = $element['#colors'];
    $chart_definition['tooltips'] = $element['#tooltips'];
    $chart_definition['foo_configuration'] = $this->configuration['foo'] ?? '';

    // Merge in chart raw options.
    if (!empty($element['#raw_options'])) {
      $chart_definition = NestedArray::mergeDeep($chart_definition, $element['#raw_options']);
    }

    return $chart_definition;
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
    $chart_definition['series'] = [];
    foreach (Element::children($element) as $key) {
      $type = $element[$key]['#type'];
      if ($type !== 'chart_data') {
        continue;
      }

      $chart_definition['series'][] = [
        'name' => $element[$key]['#title'],
        'color' => $element[$key]['#color'],
        'value' => $element[$key]['#data'],
      ];
    }

    return $chart_definition;
  }

}
