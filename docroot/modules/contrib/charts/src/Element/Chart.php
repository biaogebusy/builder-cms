<?php

namespace Drupal\charts\Element;

use Drupal\charts\Service\ChartTableBuilder;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\charts\ChartManager;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\charts\Plugin\chart\Library\ChartInterface;
use Drupal\charts\Plugin\chart\Library\LibraryRetrieverTrait;
use Drupal\charts\TypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a chart render element.
 *
 * @RenderElement("chart")
 */
class Chart extends RenderElementBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use LibraryRetrieverTrait;

  /**
   * Constructs a Chart object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\charts\ChartManager $chartsManager
   *   The chart plugin manager.
   * @param \Drupal\charts\TypeManager $chartsTypeManager
   *   The chart type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\charts\Service\ChartTableBuilder|null $tableBuilder
   *   The chart table builder service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected ChartManager $chartsManager,
    protected TypeManager $chartsTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ?ChartTableBuilder $tableBuilder = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // @phpstan-ignore-next-line
    $this->tableBuilder ??= \Drupal::service('charts.table_builder');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('plugin.manager.charts'),
      $container->get('plugin.manager.charts_type'),
      $container->get('module_handler'),
      $container->get('charts.table_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#chart_type' => NULL,
      '#chart_library' => NULL,
      '#chart_id' => NULL,
      '#title' => NULL,
      '#title_color' => '#000',
      '#title_font_weight' => 'normal',
      '#title_font_style' => 'normal',
      '#title_font_size' => 14,
      '#title_position' => 'out',
      '#subtitle' => NULL,
      '#figure_caption' => '',
      '#chart_summary' => '',
      '#accessible_table' => 'disabled',
      '#accessible_table_button_text' => '',
      '#accessible_table_button_class' => 'button',
      '#colors' => ChartBase::getDefaultColors(),
      '#font' => 'Arial',
      '#font_size' => 12,
      '#gauge' => [],
      '#background' => 'transparent',
      '#stacking' => NULL,
      '#color_changer' => FALSE,
      '#pre_render' => [
        [$this, 'preRender'],
      ],
      '#tooltips' => TRUE,
      '#tooltips_use_html' => FALSE,
      '#data_labels' => FALSE,
      '#data_markers' => FALSE,
      '#connect_nulls' => FALSE,
      '#legend' => TRUE,
      '#legend_title' => '',
      '#legend_title_font_weight' => 'bold',
      '#legend_title_font_style' => 'normal',
      '#legend_title_font_size' => '',
      '#legend_position' => 'right',
      '#legend_font_weight' => 'normal',
      '#legend_font_style' => 'normal',
      '#legend_font_size' => NULL,
      '#width' => NULL,
      '#height' => NULL,
      '#attributes' => [],
      '#chart_definition' => [],
      '#raw_options' => [],
      '#content_prefix' => [],
      '#content_suffix' => [],
      '#library_type_options' => [],
    ];
  }

  /**
   * Main #pre_render callback to expand a chart element.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The chart element.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function preRender(array $element): array {
    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface[] $definitions */
    $definitions = $this->chartsManager->getDefinitions();

    if (!$definitions) {
      $element['#type'] = 'markup';
      $element['#markup'] = $this->t('No charting library found. Enable a charting module such as Google Charts or Highcharts.');
      return $element;
    }

    // Ensure there's an x and y axis to provide defaults.
    $type_name = $element['#chart_type'];
    // Only lookup definition if a type name is provided.
    if ($type_name) {
      $type = $this->chartsTypeManager->getDefinition($type_name);
      if ($type && $type['axis'] === ChartInterface::DUAL_AXIS) {
        $children_types = [];
        foreach (Element::children($element) as $key) {
          $children_types[] = $element[$key]['#type'];
        }

        if (!in_array('chart_xaxis', $children_types)) {
          $element['xaxis'] = ['#type' => 'chart_xaxis'];
        }
        if (!in_array('chart_yaxis', $children_types)) {
          $element['yaxis'] = ['#type' => 'chart_yaxis'];
        }
      }
    }

    self::castElementIntegerValues($element);

    // Generic theme function assuming it will be suitable for most chart types.
    $element['#theme'] = 'charts_chart';
    // Allow the chart to be altered - @TODO use event dispatching if needed.
    $alter_hooks = ['chart'];
    $chart_id = $element['#chart_id'];
    if ($chart_id) {
      $alter_hooks[] = 'chart_' . $element['#chart_id'];
    }
    $this->moduleHandler->alter($alter_hooks, $element, $chart_id);

    // Include the library-specific render callback via their plugin manager.
    // Use the first charting library if the requested library is not available.
    $library = $this->getLibrary($element['#chart_library'] ?? '');

    $element['#chart_library'] = $library;
    $charts_settings = $this->configFactory->get('charts.settings');
    $plugin_configuration = $charts_settings->get('charts_default_settings.library_config') ?? [];
    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
    $plugin = $this->chartsManager->createInstance($library, $plugin_configuration);

    // Only check supported types if a type is actually declared.
    if ($type_name && !$plugin->isSupportedChartType($type_name)) {
      // Chart type not supported by the library.
      throw new \LogicException(sprintf('The provided chart type "%s" is not supported by "%s" chart plugin library.', $type_name, $plugin->getChartName()));
    }

    // If using the raw option and the user input a JSON string.
    if (!empty($element['#chart_definition']) && is_string($element['#chart_definition'])) {
      $decoded = $this->decodeLooseJson($element['#chart_definition']);
      if ($decoded !== NULL) {
        $element['#chart_definition'] = $decoded;
      }
    }

    $element = $plugin->preRender($element);

    if (!empty($element['#chart_definition'])) {
      $chart_definition = $element['#chart_definition'];
      unset($element['#chart_definition']);

      // Allow the chart definition to be altered.
      $alter_hooks = ['chart_definition'];
      if ($element['#chart_id']) {
        $alter_hooks[] = 'chart_definition_' . $chart_id;
      }

      // Ensure element is an array before passing to hooks.
      if (is_array($element)) {
        $this->moduleHandler->alter($alter_hooks, $chart_definition, $element, $chart_id);
      }

      // Set the element #chart_json property as a data-attribute.
      $element['#attributes']['data-chart'] = Json::encode($chart_definition);
    }

    // Accessible table options.
    $element = $this->accessibleTableLogic($element);

    $element['#cache']['tags'][] = 'config:charts.settings';

    return $element;
  }

  /**
   * Casts recursively integer values.
   *
   * @param array $element
   *   The element.
   */
  public static function castElementIntegerValues(array &$element): void {
    // Cast options to integers to avoid redundant library fixing problems.
    $integer_options = [
      // Chart options.
      '#title_font_size',
      '#font_size',
      '#legend_title_font_size',
      '#legend_font_size',
      '#width',
      '#height',
      // Axis options.
      '#title_font_size',
      '#labels_font_size',
      '#labels_rotation',
      '#max',
      '#min',
      // Data options.
      '#decimal_count',
    ];

    foreach ($element as $property_name => $value) {
      if (is_array($value)) {
        self::castElementIntegerValues($element[$property_name]);
      }
      elseif ($property_name && in_array($property_name, $integer_options)) {
        $element[$property_name] = (is_null($value) || strlen($value) === 0) ? NULL : (int) $value;
      }
    }
  }

  /**
   * Trims out, recursively, empty options that aren't used.
   *
   * @param array $array
   *   The array to trim.
   */
  public static function trimArray(array &$array): void {
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        self::trimArray($value);
      }
      elseif (is_null($value) || (is_array($value) && count($value) === 0)) {
        unset($array[$key]);
      }
    }
  }

  /**
   * Build the element.
   *
   * @param array $settings
   *   The settings.
   * @param string $chart_id
   *   The chart id.
   *
   * @return array
   *   The element.
   */
  public static function buildElement(array $settings, string $chart_id): array {
    $type = $settings['type'];
    $single_axis = in_array($type, ['pie', 'donut']);
    $display_colors = $settings['display']['colors'] ?? [];

    $element = [
      '#type' => 'chart',
      '#chart_type' => $type,
      '#chart_library' => $settings['library'],
      '#library_type_options' => $settings['library_type_options'] ?? [],
      '#title' => $settings['display']['title'],
      '#title_position' => $settings['display']['title_position'],
      '#subtitle' => $settings['display']['subtitle'] ?? '',
      '#figure_caption' => $settings['display']['figure_caption'] ?? '',
      '#chart_summary' => $settings['display']['chart_summary'] ?? '',
      '#accessible_table' => $settings['display']['accessible_table'] ?? 'disabled',
      '#accessible_table_button_text' => $settings['display']['accessible_table_button_text'] ?? '',
      '#accessible_table_button_class' => $settings['display']['accessible_table_button_class'] ?? '',
      '#tooltips' => $settings['display']['tooltips'] ?? [],
      '#data_labels' => $settings['display']['data_labels'] ?? FALSE,
      '#data_markers' => $settings['display']['data_markers'] ?? FALSE,
      '#connect_nulls' => $settings['display']['connect_nulls'] ?? FALSE,
      '#colors' => $display_colors,
      '#background' => $settings['display']['background'] ?? 'transparent',
      '#three_dimensional' => $settings['display']['three_dimensional'] ?? FALSE,
      '#polar' => $settings['display']['polar'] ?? FALSE,
      '#legend' => !empty($settings['display']['legend_position']),
      '#legend_position' => $settings['display']['legend_position'] ?? '',
      '#gauge' => $settings['display']['gauge'] ?? [],
      '#stacking' => !empty($settings['display']['stacking']) ?? NULL,
      '#width' => $settings['display']['dimensions']['width'],
      '#height' => $settings['display']['dimensions']['height'],
      '#width_units' => $settings['display']['dimensions']['width_units'],
      '#height_units' => $settings['display']['dimensions']['height_units'],
      '#color_changer' => $settings['display']['color_changer'] ?? FALSE,
    ];

    if (empty($settings['series'])) {
      return $element;
    }

    $table = $settings['series'];
    // Extracting the categories.
    $categories = ChartDataCollectorTable::getCategoriesFromCollectedTable($table, $type);
    // Extracting the rest of the data.
    $series_data = ChartDataCollectorTable::getSeriesFromCollectedTable($table, $type);

    $element['xaxis'] = [
      '#type' => 'chart_xaxis',
      '#labels' => $single_axis ? '' : $categories['data'],
      '#title' => $settings['xaxis']['title'] ?? FALSE,
      '#labels_rotation' => $settings['xaxis']['labels_rotation'],
    ];

    if (empty($series_data)) {
      return $element;
    }

    $element['yaxis'] = [
      '#type' => 'chart_yaxis',
      '#title' => $settings['yaxis']['title'] ?? '',
      '#labels_rotation' => $settings['yaxis']['labels_rotation'],
      '#max' => $settings['yaxis']['max'],
      '#min' => $settings['yaxis']['min'],
      '#prefix' => $settings['yaxis']['prefix'],
      '#suffix' => $settings['yaxis']['suffix'],
      '#decimal_count' => $settings['yaxis']['decimal_count'],
    ];

    // Create a secondary axis if needed.
    $series_count = count($series_data);
    if (!empty($settings['yaxis']['inherit']) && $series_count === 2) {
      $element['secondary_yaxis'] = [
        '#type' => 'chart_yaxis',
        '#title' => $settings['yaxis']['secondary']['title'] ?? '',
        '#labels_rotation' => $settings['yaxis']['secondary']['labels_rotation'],
        '#max' => $settings['yaxis']['secondary']['max'],
        '#min' => $settings['yaxis']['secondary']['min'],
        '#prefix' => $settings['yaxis']['secondary']['prefix'],
        '#suffix' => $settings['yaxis']['secondary']['suffix'],
        '#decimal_count' => $settings['yaxis']['secondary']['decimal_count'],
        '#opposite' => TRUE,
      ];
    }

    // Overriding element colors for pie and donut chart types when the
    // settings display colors is empty.
    $overrides_element_colors = !$display_colors && ($type === 'pie' || $type === 'donut');
    $series_key = $chart_id . '__series';
    if ($single_axis) {
      $new_series = [];
      $labels = [];
      foreach ($series_data as $datum) {
        $new_series[] = $datum['data'][0][1];
        $labels[] = $datum['name'];
        if ($overrides_element_colors) {
          $element['#colors'][] = $datum['color'];
        }
      }
      $element['xaxis']['#labels'] = $labels;
      // @todo Address more than one series.
      $element[$series_key] = [
        '#type' => 'chart_data',
        '#data' => $new_series,
        '#title' => $series_data[0]['title'] ?? '',
      ];
    }
    else {
      $series_counter = 0;
      foreach ($series_data as $data_index => $data) {
        $key = $series_key . '__' . $data_index;
        $element[$key] = [
          '#type' => 'chart_data',
          '#data' => $data['data'],
          '#title' => $data['name'],
        ];
        if (!empty($data['color'])) {
          $element[$key]['#color'] = $data['color'];
        }
        if (!empty($data['chart_type'])) {
          $element[$key]['#chart_type'] = $data['chart_type'];
        }
        if (isset($element['yaxis'])) {
          $element[$key]['#prefix'] = $settings['yaxis']['prefix'];
          $element[$key]['#suffix'] = $settings['yaxis']['suffix'];
          $element[$key]['#decimal_count'] = $settings['yaxis']['decimal_count'];
        }
        if (isset($element['secondary_yaxis']) && $series_counter === 1) {
          $element[$key]['#target_axis'] = 'secondary_yaxis';
          $element[$key]['#prefix'] = $settings['yaxis']['secondary']['prefix'];
          $element[$key]['#suffix'] = $settings['yaxis']['secondary']['suffix'];
          $element[$key]['#decimal_count'] = $settings['yaxis']['secondary']['decimal_count'];
        }
        $series_counter++;
      }
    }

    return $element;
  }

  /**
   * Attempts to decode a loose JSON object string.
   *
   * This method attempts to fix common syntax issues found in JavaScript
   * library examples that are not valid strict JSON.
   *
   * @param string $input
   *   The raw string input.
   *
   * @return mixed|null
   *   The decoded array, or NULL if it cannot be decoded.
   */
  protected function decodeLooseJson(string $input): ?array {
    // Try strict decode first.
    $decoded = Json::decode($input);

    if ($decoded !== NULL || json_last_error() === JSON_ERROR_NONE) {
      return $decoded;
    }

    // If strict decode fails, try to fix common "Loose JS" issues.
    $loose_json = $input;

    // Replace single quotes with double quotes, but ignore escaped
    // single quotes.
    $loose_json = str_replace("'", '"', $loose_json);

    // Quote unquoted keys, ignoring keys that already have quotes.
    // Looks for word characters at the start of the string or preceded by
    // a comma/brace, followed by a colon.
    $loose_json = preg_replace('/([{,]\s*)([a-zA-Z0-9_]+)(\s*:)/', '$1"$2"$3', $loose_json);

    // Fix trailing commas (common in JS, invalid in JSON).
    $loose_json = preg_replace('/,\s*([]}])/', '$1', $loose_json);

    return Json::decode($loose_json);
  }

  /**
   * Logic to build the accessible table.
   *
   * @param array $element
   *   The chart element.
   *
   * @return array
   *   The element with a table alternative.
   */
  protected function accessibleTableLogic(array $element): array {
    $visibility = $element['#accessible_table'] ?? 'collapsible';

    if ($visibility !== 'disabled') {
      $raw_table = $this->tableBuilder->buildTable($element);

      if ($visibility === 'collapsible') {
        $unique_id = Html::getUniqueId('charts-table-' . $element['#id']);

        // Get custom classes.
        $custom_classes = !empty($element['#accessible_table_button_class'])
          ? explode(' ', $element['#accessible_table_button_class'])
          : [];
        // Merge with required JS trigger class.
        $btn_classes = array_merge(['charts-accordion-trigger'], $custom_classes);

        $element['#table_alternative'] = [
          'trigger' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => !empty($element['#accessible_table_button_text']) ? $element['#accessible_table_button_text'] : $this->t('View data table'),
            '#attributes' => [
              'class' => $btn_classes,
              'type' => 'button',
              'aria-expanded' => 'false',
              'aria-controls' => $unique_id,
            ],
          ],
          'content' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'id' => $unique_id,
              'class' => ['charts-accordion-content'],
              'style' => 'display:none;',
            ],
            'table' => $raw_table,
          ],
        ];
      }
      elseif ($visibility === 'visible') {
        $element['#table_alternative'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['chart-table-visible', 'charts-accessible-table-wrapper']],
          'content' => $raw_table,
        ];
      }
      elseif ($visibility === 'invisible') {
        $element['#table_alternative'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['visually-hidden', 'charts-accessible-table-wrapper']],
          'content' => $raw_table,
        ];
      }
      $element['#table_visibility'] = $visibility;
    }
    else {
      // Explicitly empty variables when disabled.
      $element['#table_alternative'] = [];
      $element['#table_visibility'] = 'disabled';
    }

    return $element;
  }

}
