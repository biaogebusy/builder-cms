<?php

namespace Drupal\charts\Plugin\chart\Library;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class Chart plugins.
 */
abstract class ChartBase extends PluginBase implements ChartInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getChartName(): string {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedChartTypes(): array {
    $types = $this->pluginDefinition['types'];
    $chart_plugin_id = $this->getPluginId();
    // @todo Add dependency injection for the next major version.
    \Drupal::moduleHandler()->alter('charts_plugin_supported_chart_types', $types, $chart_plugin_id);
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function isSupportedChartType(string $chart_type_id): bool {
    $supported_chart_types = $this->getSupportedChartTypes();
    return !$supported_chart_types || in_array($chart_type_id, $supported_chart_types);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Gets defaults settings.
   *
   * @return array
   *   The defaults settings.
   */
  public static function getDefaultSettings(): array {
    return [
      'type' => 'line',
      'library' => NULL,
      'grouping' => FALSE,
      'fields' => [
        'label' => NULL,
        'data_providers' => NULL,
      ],
      'display' => [
        'title' => '',
        'title_position' => 'out',
        'data_labels' => FALSE,
        'data_markers' => TRUE,
        'legend' => TRUE,
        'legend_position' => 'right',
        'background' => '',
        'three_dimensional' => FALSE,
        'polar' => FALSE,
        'tooltips' => TRUE,
        'tooltips_use_html' => FALSE,
        'dimensions' => [
          'width' => NULL,
          'width_units' => '%',
          'height' => NULL,
          'height_units' => 'px',
        ],
        'gauge' => [
          'green_to' => 100,
          'green_from' => 85,
          'yellow_to' => 85,
          'yellow_from' => 50,
          'red_to' => 50,
          'red_from' => 0,
          'max' => 100,
          'min' => 0,
        ],
        'colors' => self::getDefaultColors(),
      ],
    ];
  }

  /**
   * Gets the default hex colors.
   *
   * @return array
   *   The hex colors.
   */
  public static function getDefaultColors(): array {
    return [
      '#2f7ed8',
      '#0d233a',
      '#8bbc21',
      '#910000',
      '#1aadce',
      '#492970',
      '#f28f43',
      '#77a1e5',
      '#c42525',
      '#a6c96a',
    ];
  }

}
