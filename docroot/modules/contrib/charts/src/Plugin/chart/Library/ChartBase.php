<?php

namespace Drupal\charts\Plugin\chart\Library;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class Chart plugins.
 */
abstract class ChartBase extends PluginBase implements ChartInterface {

  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a Base Chart object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ?ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (empty($module_handler)) {
      // @phpstan-ignore-next-line
      $module_handler = \Drupal::service('module_handler');
      @trigger_error('Calling ChartBase::__construct() without the $module_handler as an instance of ModuleHandlerInterface is deprecated in charts:5.1.6 and is required in charts:6.0.0. See https://www.drupal.org/project/charts/issues/3518027', E_USER_DEPRECATED);
    }
    $this->moduleHandler = $module_handler;
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
    );
  }

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
    $this->moduleHandler->alter('charts_plugin_supported_chart_types', $types, $chart_plugin_id);

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
   * {@inheritdoc}
   */
  public function addBaseSettingsElementOptions(array &$element, array $options, FormStateInterface $form_state, array &$complete_form = []): void {
    // Hide the fieldset by default.
    $element['#access'] = FALSE;
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
