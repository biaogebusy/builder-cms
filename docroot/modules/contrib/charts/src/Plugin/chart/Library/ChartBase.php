<?php

namespace Drupal\charts\Plugin\chart\Library;

use Drupal\charts\Form\BaseColorChanger;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
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
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

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
   * @param \Drupal\Core\Form\FormBuilderInterface|null $form_builder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ?ModuleHandlerInterface $module_handler = NULL, ?FormBuilderInterface $form_builder = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (empty($module_handler)) {
      // @phpstan-ignore-next-line
      $module_handler = \Drupal::service('module_handler');
      @trigger_error('Calling ChartBase::__construct() without the $module_handler as an instance of ModuleHandlerInterface is deprecated in charts:5.1.6 and is required in charts:6.0.0. See https://www.drupal.org/project/charts/issues/3518027', E_USER_DEPRECATED);
    }
    $this->moduleHandler = $module_handler;

    if (empty($form_builder)) {
      // @phpstan-ignore-next-line
      $form_builder = \Drupal::service('form_builder');
      @trigger_error('Calling ChartBase::__construct() without the $form_builder as an instance of FormBuilderInterface is deprecated in charts:5.1.11 and is required in charts:6.0.0. See https://www.drupal.org/project/charts/issues/3535784', E_USER_DEPRECATED);
    }
    $this->formBuilder = $form_builder;
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
      $container->get('form_builder'),
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
    $types = array_diff($this->pluginDefinition['types'], $this->getUnsupportedChartTypes());
    $chart_plugin_id = $this->getPluginId();
    $this->moduleHandler->alter('charts_plugin_supported_chart_types', $types, $chart_plugin_id);

    return array_values($types);
  }

  /**
   * Gets chart types the library declares but cannot currently render.
   *
   * A library may add whole chart types through an optional asset (for example,
   * a separate "heatmap" script). When that asset is disabled through the
   * library's configuration, the types it provides should not be offered.
   * Subclasses override this method to remove those types; the
   * charts_plugin_supported_chart_types alter hook still runs afterwards, so
   * this is additive rather than a replacement for the hook.
   *
   * Note this concerns type-*providing* libraries only. Feature libraries that
   * merely enhance an existing type (a Pareto line, a color axis) must not be
   * listed here: their options stay available and their assets load on demand.
   *
   * @return string[]
   *   Chart type IDs to exclude from the supported types.
   */
  protected function getUnsupportedChartTypes(): array {
    return [];
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

  /**
   * A form builder utility for the color changer.
   *
   * @param array $form_state_items
   *   The form state items.
   *
   * @return array
   *   The built form.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  protected function colorChangerFormBuilder(array $form_state_items): array {
    $form_state = new FormState();
    foreach ($form_state_items as $item_key => $item_value) {
      $form_state->set($item_key, $item_value);
    }
    return $this->formBuilder->buildForm(BaseColorChanger::class, $form_state);
  }

}
