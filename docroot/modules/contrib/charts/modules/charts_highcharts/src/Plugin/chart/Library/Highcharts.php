<?php

namespace Drupal\charts_highcharts\Plugin\chart\Library;

use Drupal\charts\ApplyRawOptionsTrait;
use Drupal\charts\Attribute\Chart;
use Drupal\charts\Element\Chart as ChartElement;
use Drupal\charts\Plugin\chart\Library\ChartBase;
use Drupal\charts\TypeManager;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The 'Highcharts' chart type attribute.
 */
#[Chart(
  id: "highcharts",
  name: new TranslatableMarkup("Highcharts"),
  types: [
    "area",
    "arearange",
    "bar",
    "boxplot",
    "bubble",
    "column",
    "donut",
    "dumbbell",
    "gauge",
    "heatmap",
    "line",
    "pie",
    "scatter",
    "solidgauge",
    "spline",
    "treemap",
  ],
  example_route: "charts_highcharts_api_example.display",
)]
class Highcharts extends ChartBase implements ContainerFactoryPluginInterface {

  use ApplyRawOptionsTrait;

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
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info
   *   The element info manager.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   The configuration factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElementInfoManagerInterface $element_info, TypeManager $chart_type_manager, FormBuilderInterface $form_builder, ?ModuleHandlerInterface $module_handler = NULL, protected ?ConfigFactoryInterface $configFactory = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $form_builder);
    $this->elementInfo = $element_info;
    $this->chartTypeManager = $chart_type_manager;

    if (empty($configFactory)) {
      // @phpstan-ignore-next-line
      $this->configFactory = \Drupal::service('config.factory');
      @trigger_error('Calling Highcharts::__construct() without the $configFactory argument is deprecated in charts:5.2.3 and is required in charts:6.0.0. See https://www.drupal.org/node/3540000', E_USER_DEPRECATED);
    }
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
      $container->get('plugin.manager.charts_type'),
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('config.factory'),
    );
  }

  /**
   * Maps a library toggle to the chart types it makes available.
   *
   * This is the single source of truth for which optional Highcharts modules
   * add whole chart *types*, and it is the only thing gated by the library
   * configuration: when one of these toggles is off, its types drop out of the
   * chart-type dropdown. Adding a new type-providing module is a one-line
   * change here.
   *
   * Feature libraries that merely enhance an existing type (Color Axis for
   * pie/donut/treemap, Pareto for bar/column) are deliberately NOT listed:
   * their options must remain visible for the relevant type and their assets
   * load on demand when the feature is used. Gating those here would hide the
   * type-specific options form.
   *
   * @return array<string, string[]>
   *   Keyed by configuration key; each value is the list of types it provides.
   */
  protected function typeProvidingLibraries(): array {
    return [
      'dumbbell_library' => ['dumbbell'],
      'heatmap_library' => ['heatmap'],
      'solidgauge_library' => ['solidgauge'],
      'treemap_library' => ['treemap'],
    ];
  }

  /**
   * Maps a feature library toggle to the option element keys it gates.
   *
   * Feature libraries enhance existing chart types rather than adding new ones,
   * so they never remove a type from the dropdown. Their option elements are
   * shown only when the library is enabled; the surrounding options fieldset
   * stays available, and the asset still loads on demand when the feature is
   * actually used (see preRender()). Adding a new feature library is a one-line
   * change here.
   *
   * @return array<string, string[]>
   *   Keyed by configuration key; each value is the list of option element keys
   *   the library gates.
   */
  protected function featureLibraries(): array {
    return [
      'coloraxis_library' => ['coloraxis', 'min_color', 'max_color'],
      'pareto_library' => ['pareto_line', 'pareto_color'],
    ];
  }

  /**
   * Resolves the effective library configuration for this plugin.
   *
   * Per-instance configuration (an entity or view override) takes precedence;
   * anything missing falls back to the site-wide settings for *this* library.
   * The global lookup mirrors the resolution used elsewhere in the module
   * (see \Drupal\charts\Element\BaseSettings): the per-library
   * "library_configs" bucket keyed by plugin id, falling back to
   * "library_config" only when this plugin is the configured default library.
   *
   * @return array
   *   The merged library configuration.
   */
  protected function resolvedLibraryConfig(): array {
    $settings = $this->configFactory->get('charts.settings');
    $library_configs = $settings->get('charts_default_settings.library_configs') ?? [];
    $global = $library_configs[$this->getPluginId()]
      ?? ($settings->get('charts_default_settings.library_config') ?? []);

    return $this->configuration + $global;
  }

  /**
   * Determines whether an optional library is enabled.
   *
   * @param string $key
   *   The configuration key, e.g. "heatmap_library".
   *
   * @return bool
   *   TRUE when the optional library is enabled for this plugin.
   */
  protected function optionalLibraryEnabled(string $key): bool {
    return !empty($this->resolvedLibraryConfig()[$key]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getUnsupportedChartTypes(): array {
    $unsupported = [];
    foreach ($this->typeProvidingLibraries() as $key => $types) {
      if (!$this->optionalLibraryEnabled($key)) {
        $unsupported = array_merge($unsupported, $types);
      }
    }

    return $unsupported;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configurations = [
      'legend' => [
        'layout' => NULL,
        'background_color' => '',
        'border_width' => 0,
        'shadow' => FALSE,
        'item_style' => [
          'color' => '',
          'overflow' => '',
        ],
      ],
      '3d_library' => TRUE,
      'accessibility_library' => TRUE,
      'annotations_library' => FALSE,
      'boost_library' => FALSE,
      'coloraxis_library' => FALSE,
      'data_library' => FALSE,
      'dumbbell_library' => FALSE,
      'exporting_library' => TRUE,
      'heatmap_library' => FALSE,
      'no_data_library' => FALSE,
      'pareto_library' => FALSE,
      'texture_library' => FALSE,
      'treemap_library' => FALSE,
      'solidgauge_library' => FALSE,
      'disable_default_css_library' => FALSE,
      'global_options' => static::defaultGlobalOptions(),
    ] + parent::defaultConfiguration();

    return $configurations;
  }

  /**
   * Build configurations.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Return the form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['intro_text'] = [
      '#markup' => $this->t('<p>Charts is designed to be generic enough to work with multiple charting libraries. If you would like settings that apply to all Highcharts charts, you can <a href="https://www.drupal.org/project/issues/charts" target="_blank">submit a ticket</a> to have a setting added here, in the Highcharts-specific settings.</p>'),
    ];

    $form['3d_library'] = [
      '#title' => $this->t('Enable Highcharts\' "3D" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['3d_library']),
      '#description' => $this->t('Highcharts 3D module is a separate library that enables 3D charts. See <a href="https://www.highcharts.com/docs/chart-concepts/3d-charts" target="_blank">Highcharts 3D documentation</a> for more information.'),
    ];

    $form['accessibility_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Accessibility" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['accessibility_library']),
      '#description' => $this->t('Highcharts Accessibility module is a separate library that enables accessibility features. See <a href="https://www.highcharts.com/docs/chart-concepts/accessibility" target="_blank">Highcharts Accessibility documentation</a> for more information.'),
    ];

    $form['annotations_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Annotations" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['annotations_library']),
      '#description' => $this->t('Highcharts Annotations module is a separate library that enables annotations. See <a href="https://www.highcharts.com/docs/advanced-chart-features/annotations-module" target="_blank">Highcharts Annotations documentation</a> for more information.'),
    ];

    $form['boost_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Boost" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['boost_library']),
      '#description' => $this->t('Highcharts Boost module is a separate library that enables faster rendering of charts. See <a href="https://www.highcharts.com/docs/advanced-chart-features/boost-module" target="_blank">Highcharts Boost documentation</a> for more information.'),
    ];

    $form['coloraxis_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Color Axis" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['coloraxis_library']),
      '#description' => $this->t('Highcharts Color Axis module is a separate library that enables color axis. See <a href="https://www.highcharts.com/docs/chart-and-series-types/color-axis" target="_blank">Highcharts Color Axis documentation</a> for more information.'),
    ];

    $form['data_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Data" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['data_library']),
      '#description' => $this->t('Highcharts Data module is a separate library that enables data import and export. See <a href="https://www.highcharts.com/docs/working-with-data/data-module" target="_blank">Highcharts Data documentation</a> for more information.'),
    ];

    $form['dumbbell_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Dumbbell" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['dumbbell_library']),
      '#description' => $this->t('Highcharts Dumbbell module is a separate library that enables the dumbbell chart style. See <a href="https://www.highcharts.com/docs/chart-and-series-types/dumbbell-series" target="_blank">Highcharts Dumbbell documentation</a> for more information.'),
    ];

    $form['exporting_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Exporting" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['exporting_library']),
      '#description' => $this->t('Highcharts Exporting module is a separate library that enables exporting charts. See <a href="https://www.highcharts.com/docs/export-module/export-module-overview" target="_blank">Highcharts Exporting documentation</a> for more information.'),
    ];

    $form['heatmap_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Heatmap" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['heatmap_library']),
      '#description' => $this->t('Highcharts Heatmap module is a separate library that enables heatmap charts. See <a href="https://www.highcharts.com/docs/chart-and-series-types/heatmap-series" target="_blank">Highcharts Heatmap documentation</a> for more information.'),
    ];

    $form['no_data_library'] = [
      '#title' => $this->t('Enable Highcharts\' "No Data" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['no_data_library']),
      '#description' => $this->t('Highcharts No Data module is a separate library that enables no data message. See <a href="https://api.highcharts.com/highcharts/noData" target="_blank">Highcharts No Data documentation</a> for more information.'),
    ];

    $form['pareto_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Pareto" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['pareto_library']),
      '#description' => $this->t('Highcharts Pareto module enables pareto series types.'),
    ];

    $form['texture_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Texture" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['texture_library']),
      '#description' => $this->t('Highcharts Texture module is a separate library that enables texture fill. See <a href="https://www.highcharts.com/docs/chart-design-and-style/pattern-fills" target="_blank">Highcharts Texture documentation</a> for more information.'),
    ];

    $form['solidgauge_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Solid Gauge" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['solidgauge_library']),
      '#description' => $this->t('Highcharts Texture module is a separate library that enables texture fill. See <a href="https://api.highcharts.com/highcharts/series.solidgauge" target="_blank">Solid Gauge documentation</a> for more information.'),
    ];

    $form['treemap_library'] = [
      '#title' => $this->t('Enable Highcharts\' "Treemap" library'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['treemap_library']),
      '#description' => $this->t('Highcharts Treemap module is a separate library that enables treemap charts. See <a href="https://www.highcharts.com/docs/chart-and-series-types/treemap" target="_blank">Highcharts Treemap documentation</a> for more information.'),
    ];

    // Provide option to disable adding the default Highcharts CSS library.
    if (!empty($this->configuration['global_options']['chart']['styled_mode'])) {
      $form['disable_default_css_library'] = [
        '#title' => $this->t("Disable the default Highcharts' CSS library"),
        '#type' => 'checkbox',
        '#default_value' => !empty($this->configuration['disable_default_css_library']),
        '#description' => $this->t('When styledMode is enabled in the global chart options, the default highcharts.css library is added by default. Check this box to disable adding the default CSS.'),
      ];
    }
    else {
      // Provide default value to the submit handler, if styled_mode
      // is not enabled.
      $form['disable_default_css_library'] = [
        '#type' => 'value',
        '#value' => $this->defaultConfiguration()['disable_default_css_library'],
      ];
    }

    $legend_configuration = $this->configuration['legend'] ?? [];
    $form['legend'] = [
      '#title' => $this->t('Legend Settings'),
      '#type' => 'fieldset',
    ];
    $form['legend']['layout'] = [
      '#title' => $this->t('Legend layout'),
      '#type' => 'select',
      '#options' => [
        '' => $this->t('Auto'),
        'vertical' => $this->t('Vertical'),
        'horizontal' => $this->t('Horizontal'),
      ],
      '#default_value' => $legend_configuration['layout'] ?? NULL,
    ];
    $form['legend']['background_color'] = [
      '#title' => $this->t('Legend background color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => ['placeholder' => $this->t('transparent')],
      '#description' => $this->t('Leave blank for a transparent background.'),
      '#default_value' => $legend_configuration['background_color'] ?? '',
    ];
    $form['legend']['border_width'] = [
      '#title' => $this->t('Legend border width'),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('None'),
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
      ],
      '#default_value' => $legend_configuration['border_width'] ?? 0,
    ];
    $form['legend']['shadow'] = [
      '#title' => $this->t('Enable legend shadow'),
      '#type' => 'checkbox',
      '#default_value' => !empty($legend_configuration['shadow']),
    ];
    $form['legend']['item_style'] = [
      '#title' => $this->t('Item Style'),
      '#type' => 'fieldset',
    ];
    $form['legend']['item_style']['color'] = [
      '#title' => $this->t('Item style color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => ['placeholder' => '#333333'],
      '#description' => $this->t('Leave blank for a dark gray font.'),
      '#default_value' => $legend_configuration['item_style']['color'] ?? '',
    ];
    $form['legend']['item_style']['overflow'] = [
      '#title' => $this->t('Text overflow'),
      '#type' => 'select',
      '#options' => [
        '' => $this->t('No'),
        'ellipsis' => $this->t('Ellipsis'),
      ],
      '#default_value' => $legend_configuration['item_style']['overflow'] ?? '',
    ];

    $form['global_options'] = [
      '#title' => $this->t('Global options'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    ];
    $form['global_options']['lang'] = [
      '#title' => $this->t('Language'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    ];
    // Download menu item.
    $lang_config = $this->defaultConfiguration()['global_options']['lang'];
    foreach (array_keys($lang_config) as $property) {
      if (strpos($property, 'download_') !== 0) {
        continue;
      }

      [, $format] = explode('_', $property);
      $form['global_options']['lang'][$property] = [
        '#title' => $this->t('Download @format', ['@format' => $format]),
        '#type' => 'textfield',
        '#description' => $this->t('The text for the @format download menu item.', ['@format' => $format]),
        '#default_value' => $this->configuration['global_options']['lang'][$property] ?? $lang_config[$property],
        '#required' => TRUE,
      ];
    }
    // Other simple string configs.
    $form['global_options']['lang']['exit_fullscreen'] = [
      '#title' => $this->t('Exit fullscreen'),
      '#type' => 'textfield',
      '#description' => $this->t('Exporting module only. The text for the menu item to exit the chart from full screen.'),
      '#default_value' => $this->configuration['global_options']['lang']['exit_fullscreen'] ?? $lang_config['exit_fullscreen'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['hide_data'] = [
      '#title' => $this->t('Hide data'),
      '#type' => 'textfield',
      '#description' => $this->t('The text for the menu item.'),
      '#default_value' => $this->configuration['global_options']['lang']['hide_data'] ?? $lang_config['hide_data'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['loading'] = [
      '#title' => $this->t('Loading'),
      '#type' => 'textfield',
      '#description' => $this->t('The loading text that appears when the chart is set into the loading state following a call to <code>chart.showLoading</code>.'),
      '#default_value' => $this->configuration['global_options']['lang']['loading'] ?? $lang_config['loading'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['main_breadcrumb'] = [
      '#title' => $this->t('Main breadcrumb'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['global_options']['lang']['main_breadcrumb'] ?? $lang_config['main_breadcrumb'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['thousands_sep'] = [
      '#title' => $this->t('Number formatting: Thousand separator'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['global_options']['lang']['thousands_sep'] ?? $lang_config['thousands_sep'],
    ];
    $form['global_options']['lang']['decimal_point'] = [
      '#title' => $this->t('Number formatting: Decimal point'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['global_options']['lang']['decimal_point'] ?? $lang_config['decimal_point'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['no_data'] = [
      '#title' => $this->t('No data'),
      '#type' => 'textfield',
      '#description' => $this->t('The text to display when the chart contains no data.'),
      '#default_value' => $this->configuration['global_options']['lang']['no_data'] ?? $lang_config['no_data'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['print_chart'] = [
      '#title' => $this->t('Print chart'),
      '#type' => 'textfield',
      '#description' => $this->t('Exporting module only. The text for the menu item to print the chart.'),
      '#default_value' => $this->configuration['global_options']['lang']['print_chart'] ?? $lang_config['print_chart'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['reset_zoom'] = [
      '#title' => $this->t('Reset zoom'),
      '#type' => 'textfield',
      '#description' => $this->t('The text for the label appearing when a chart is zoomed.'),
      '#default_value' => $this->configuration['global_options']['lang']['reset_zoom'] ?? $lang_config['reset_zoom'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['reset_zoom_title'] = [
      '#title' => $this->t('Reset zoom title'),
      '#type' => 'textfield',
      '#description' => $this->t('The tooltip title for the label appearing when a chart is zoomed.'),
      '#default_value' => $this->configuration['global_options']['lang']['reset_zoom_title'] ?? $lang_config['reset_zoom_title'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['view_data'] = [
      '#title' => $this->t('View data'),
      '#type' => 'textfield',
      '#description' => $this->t('The text for the menu item.'),
      '#default_value' => $this->configuration['global_options']['lang']['view_data'] ?? $lang_config['view_data'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['view_fullscreen'] = [
      '#title' => $this->t('View fullscreen'),
      '#type' => 'textfield',
      '#description' => $this->t('Exporting module only. The text for the menu item to view the chart in full screen.'),
      '#default_value' => $this->configuration['global_options']['lang']['view_fullscreen'] ?? $lang_config['view_fullscreen'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['context_button_title'] = [
      '#title' => $this->t('Context button title'),
      '#type' => 'textfield',
      '#description' => $this->t('Exporting module menu. The tooltip title for the context menu holding print and export menu items.'),
      '#default_value' => $this->configuration['global_options']['lang']['context_button_title'] ?? $lang_config['context_button_title'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['drill_up_text'] = [
      '#title' => $this->t('Drill up text'),
      '#type' => 'textfield',
      '#description' => $this->t('The text for the button that appears when drilling down, linking back to the parent series.'),
      '#default_value' => $this->configuration['global_options']['lang']['drill_up_text'] ?? $lang_config['drill_up_text'],
      '#required' => TRUE,
    ];
    $form['global_options']['lang']['invalid_date'] = [
      '#title' => $this->t('Invalid date'),
      '#type' => 'textfield',
      '#description' => $this->t('What to show in a date field for invalid dates.'),
      '#default_value' => $this->configuration['global_options']['lang']['invalid_date'] ?? $lang_config['invalid_date'],
      '#required' => TRUE,
    ];
    // Dates related global options.
    foreach ($this->datesDataForConfigForm() as $dates_data_key => $data) {
      $form['global_options']['lang'][$dates_data_key] = [
        '#type' => 'details',
        '#title' => $data['label_plural'],
        '#description' => $data['description'],
        '#collapsible' => TRUE,
        '#tree' => TRUE,
      ];
      foreach (range(0, $data['range_end']) as $counter) {
        $form['global_options']['lang'][$dates_data_key][$counter] = [
          '#title' => $this->t('@label_singular', [
            '@label_singular' => $data['label_singular'],
          ]) . ' ' . ($counter + 1),
          '#type' => 'textfield',
          '#default_value' => $this->configuration['global_options']['lang'][$dates_data_key][$counter] ?? $lang_config[$dates_data_key][$counter],
          '#required' => TRUE,
        ];
      }
    }
    // Export data related global options.
    $form['global_options']['lang']['export_data'] = [
      '#title' => $this->t('Export data'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    ];
    foreach ($this->exportDataForConfigForm() as $export_data_key => $data) {
      $form['global_options']['lang']['export_data'][$export_data_key] = [
        '#title' => $this->t('@label', [
          '@label' => $data['label'],
        ]),
        '#type' => 'textfield',
        '#description' => $this->t('@description', [
          '@description' => $data['description'],
        ]),
        '#default_value' => $this->configuration['global_options']['lang']['export_data'][$export_data_key] ?? $lang_config['export_data'][$export_data_key],
        '#required' => TRUE,
      ];
    }
    // Numeric symbols related global options.
    $form['global_options']['lang']['numeric_symbols'] = [
      '#type' => 'details',
      '#title' => 'Numeric symbols',
      '#description' => t('The numeric symbols.'),
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    ];
    foreach (range(0, 5) as $counter) {
      $form['global_options']['lang']['numeric_symbols'][$counter] = [
        '#title' => $this->t('@label_singular', [
          '@label_singular' => 'Numeric symbol',
        ]) . ' ' . ($counter + 1),
        '#type' => 'textfield',
        '#default_value' => $this->configuration['global_options']['lang']['numeric_symbols'][$counter] ?? $lang_config['numeric_symbols'][$counter],
        '#required' => TRUE,
      ];
    }
    // Add global options for the chart property.
    $form['global_options']['chart'] = [
      '#title' => $this->t('Chart'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#tree' => TRUE,
    ];
    $form['global_options']['chart']['styled_mode'] = [
      '#title' => $this->t('Enable styled mode'),
      '#description' => $this->t('Enables styledMode for the chart. This will include the default highcharts.css and apply styles from the CSS files instead of inline styles. For example for dark mode support. See <a href=":url" target="_blank">chart.styledMode</a> for more information.', [
        ':url' => 'https://api.highcharts.com/highcharts/chart.styledMode',
      ]),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->configuration['global_options']['chart']['styled_mode']),
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
      $this->configuration['legend'] = $values['legend'];
      $this->configuration['3d_library'] = $values['3d_library'];
      $this->configuration['accessibility_library'] = $values['accessibility_library'];
      $this->configuration['annotations_library'] = $values['annotations_library'];
      $this->configuration['boost_library'] = $values['boost_library'];
      $this->configuration['coloraxis_library'] = $values['coloraxis_library'];
      $this->configuration['dumbbell_library'] = $values['dumbbell_library'];
      $this->configuration['data_library'] = $values['data_library'];
      $this->configuration['exporting_library'] = $values['exporting_library'];
      $this->configuration['heatmap_library'] = $values['heatmap_library'];
      $this->configuration['no_data_library'] = $values['no_data_library'];
      $this->configuration['pareto_library'] = $values['pareto_library'];
      $this->configuration['solidgauge_library'] = $values['solidgauge_library'];
      $this->configuration['texture_library'] = $values['texture_library'];
      $this->configuration['treemap_library'] = $values['treemap_library'];
      $this->configuration['disable_default_css_library'] = $values['disable_default_css_library'];
      $this->configuration['global_options'] = $values['global_options'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addBaseSettingsElementOptions(array &$element, array $options, FormStateInterface $form_state, array &$complete_form = []): void {

    // The types to be processed in this method.
    $extra_options_types = [
      'bar',
      'column',
      'donut',
      'dumbbell',
      'pie',
      'solidgauge',
      'treemap',
    ];

    if (!in_array($element['#chart_type'], $extra_options_types)) {
      parent::addBaseSettingsElementOptions($element, $options, $form_state, $complete_form);
      return;
    }

    // Settings for donut and pie.
    if (in_array($element['#chart_type'], ['donut', 'pie'])) {
      $this->processDonutPieOptions($element, $options);
    }

    // Settings for solidgauge.
    if ($element['#chart_type'] === 'solidgauge') {
      $this->processSolidGaugeOptions($element, $options);
    }

    // Settings for treemap.
    if ($element['#chart_type'] === 'treemap') {
      $this->processColorAxisOptions($element, $options);
    }

    // Settings for dumbbell.
    if ($element['#chart_type'] === 'dumbbell') {
      $this->processDumbbellOptions($element, $options);
    }

    // Settings for bar and column.
    if (in_array($element['#chart_type'], ['bar', 'column'])) {
      $element['enable_stacklabels'] = [
        '#title' => $this->t('Enable stackLabels'),
        '#type' => 'checkbox',
        '#default_value' => !empty($options['enable_stacklabels']),
        '#description' => $this->t('Enable stackLabels for stacked bar or column charts.'),
      ];

      $element['pareto_line'] = [
        '#title' => $this->t('Enable Pareto line'),
        '#type' => 'checkbox',
        '#default_value' => !empty($options['pareto_line']),
      ];

      $element['pareto_color'] = [
        '#title' => $this->t('Pareto line color'),
        '#type' => 'textfield',
        '#size' => 10,
        '#maxlength' => 7,
        '#attributes' => ['TYPE' => 'color'],
        '#default_value' => $options['pareto_color'] ?? '#000000',
        '#states' => [
          'visible' => [
            ':input[name*="pareto_line"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Feature libraries (Color Axis, Pareto) enhance existing chart types.
    // Their option elements are offered only when the corresponding library is
    // enabled; the chart type and the surrounding fieldset stay available. The
    // asset still loads on demand when the feature is used (see preRender()).
    foreach ($this->featureLibraries() as $key => $option_keys) {
      if ($this->optionalLibraryEnabled($key)) {
        continue;
      }
      foreach ($option_keys as $option_key) {
        unset($element[$option_key]);
      }
    }

    // The Color Axis types (pie, donut, treemap) have no other options, so
    // disabling that library leaves the fieldset empty. Keep it visible but
    // explain why, so the section is discoverable and does not look broken.
    if (!Element::children($element) && !$this->optionalLibraryEnabled('coloraxis_library')) {
      $element['empty_message'] = [
        '#type' => 'item',
        '#markup' => $this->t('Enable the Color Axis library to configure color options for this chart type.'),
      ];
    }
  }

  /**
   * Process solid gauge options.
   *
   * @param array $element
   *   The form element.
   * @param array $options
   *   The options array.
   */
  private function processSolidGaugeOptions(array &$element, array &$options): void {
    $solidgauge_options = $options + [
      'max' => 100,
      'min' => 0,
      'stops' => [
        ['position' => 0, 'color' => ''],
        ['position' => 0.25, 'color' => ''],
        ['position' => 0.5, 'color' => ''],
        ['position' => 1, 'color' => ''],
      ],
    ];
    $element['max'] = [
      '#title' => $this->t('Solid gauge maximum value'),
      '#type' => 'number',
      '#default_value' => $solidgauge_options['max'],
    ];
    $element['min'] = [
      '#title' => $this->t('Solid gauge minimum value'),
      '#type' => 'number',
      '#default_value' => $solidgauge_options['min'],
    ];
    $element['stops'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stops'),
      '#tree' => TRUE,
    ];
    foreach (range(0, 3) as $stop_index) {
      $element['stops'][$stop_index] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Stop @index', ['@index' => $stop_index + 1]),
      ];
      $element['stops'][$stop_index]['position'] = [
        '#type' => 'number',
        '#title' => $this->t('Position'),
        '#description' => $this->t('Value between 0 and 1'),
        '#min' => 0,
        '#max' => 1,
        '#step' => '.01',
        '#size' => 5,
        '#default_value' => $solidgauge_options['stops'][$stop_index]['position'],
        '#wrapper_attributes' => [
          'style' => 'display: inline-block; margin-right: 20px; vertical-align: top;',
        ],
      ];
      $element['stops'][$stop_index]['color'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Color'),
        '#attributes' => [
          'TYPE' => 'color',
          'style' => 'min-width:50px;',
        ],
        '#size' => 10,
        '#maxlength' => 7,
        '#default_value' => $solidgauge_options['stops'][$stop_index]['color'],
        '#wrapper_attributes' => [
          'style' => 'display: inline-block; vertical-align: top;',
        ],
      ];
    }
  }

  /**
   * Process donut/pie options.
   *
   * @param array $element
   *   The form element.
   * @param array $options
   *   The options array.
   */
  private function processDonutPieOptions(array &$element, array &$options): void {
    $this->processColorAxisOptions($element, $options);
  }

  /**
   * Process colorAxis options.
   *
   * @param array $element
   *   The form element.
   * @param array $options
   *   The options array.
   */
  private function processColorAxisOptions(array &$element, array &$options): void {
    $element['coloraxis'] = [
      '#title' => $this->t('Enable colorAxis'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['coloraxis']),
      '#description' => $this->t('Enable color axis for pie charts.'),
    ];
    // Minimum color value.
    $element['min_color'] = [
      '#title' => $this->t('Minimum color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => [
        'placeholder' => '#FFFFFF',
        'TYPE' => 'color',
      ],
      '#description' => $this->t('The color to use for the minimum value. Leave blank for no minimum color.'),
      '#default_value' => $options['min_color'] ?? '#FFFFFF',
    ];
    // Maximum color value.
    $element['max_color'] = [
      '#title' => $this->t('Maximum color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => [
        'placeholder' => '#000000',
        'TYPE' => 'color',
      ],
      '#description' => $this->t('The color to use for the maximum value. Leave blank for no maximum color.'),
      '#default_value' => $options['max_color'] ?? '#000000',
    ];
  }

  /**
   * Process dumbbell options.
   *
   * @param array $element
   *   The form element.
   * @param array $options
   *   The options array.
   */
  private function processDumbbellOptions(array &$element, array &$options): void {
    // Minimum color value.
    $element['low_color'] = [
      '#title' => $this->t('Low color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => [
        'placeholder' => '#FFFFFF',
        'TYPE' => 'color',
      ],
      '#description' => $this->t('The color to use for the low value. Leave blank for no low color.'),
      '#default_value' => $options['low_color'] ?? '#FFFFFF',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(array $element) {
    // Check if a raw chart definition is already provided.
    // This allows bypassing the Drupal Charts abstraction layer.
    if (!empty($element['#chart_definition'])) {
      $chart_definition = $element['#chart_definition'];
    }
    else {
      // Standard processing: Populate chart settings from Drupal
      // element properties.
      $chart_definition = [];
      $chart_definition = $this->populateOptions($element, $chart_definition);
      $chart_definition = $this->populateAxes($element, $chart_definition);
      $chart_definition = $this->populateData($element, $chart_definition);

      // Remove machine names from series. Highcharts series must be an array.
      $series = !empty($chart_definition['series']) ? array_values($chart_definition['series']) : [];
      unset($chart_definition['series']);

      // Trim out empty options (excluding "series" for efficiency).
      ChartElement::trimArray($chart_definition);

      // Put back the data.
      $chart_definition['series'] = $series;
    }

    // Handle container sizing.
    if (!empty($element['#height']) || !empty($element['#width'])) {
      $height = $element['#height'] . $element['#height_units'];
      $width = $element['#width'] . $element['#width_units'];
      $element['#attributes']['style'] = "height:{$height};width:{$width};";
    }

    if (!isset($element['#id'])) {
      $element['#id'] = Html::getUniqueId('highchart-render');
    }

    // Attach libraries.
    $element['#attached']['library'][] = 'charts_highcharts/highcharts';

    // Attach optional libraries based on configuration.
    if (!empty($this->configuration['3d_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/3d';
    }
    if (!empty($this->configuration['accessibility_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/accessibility';
    }
    if (!empty($this->configuration['annotations_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/annotations';
    }
    if (!empty($this->configuration['boost_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/boost';
    }
    if (!empty($this->configuration['coloraxis_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/coloraxis';
      // If we are in standard mode, we might need to clean data colors.
      if (empty($element['#chart_definition']) && !empty($chart_definition['colorAxis'])) {
        foreach ($chart_definition['series'] as &$series_to_clean) {
          if (isset($series_to_clean['color'])) {
            unset($series_to_clean['color']);
          }
          if (isset($series_to_clean['data'])) {
            foreach ($series_to_clean['data'] as &$data_to_clean) {
              if (!empty($data_to_clean['color'])) {
                unset($data_to_clean['color']);
              }
            }
          }
        }
      }
    }
    if (!empty($this->configuration['data_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/data';
    }
    if (!empty($this->configuration['dumbbell_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/dumbbell';
    }
    if (!empty($this->configuration['exporting_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/exporting';
    }
    if (!empty($this->configuration['heatmap_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/heatmap';
    }
    if (!empty($this->configuration['no_data_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/no_data';
    }
    if (!empty($this->configuration['solidgauge_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/solidgauge';
    }
    if (!empty($this->configuration['pareto_library']) || !empty($element['#library_type_options']['pareto_line'])) {
      $element['#attached']['library'][] = 'charts_highcharts/pareto';
    }
    if (!empty($this->configuration['texture_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/texture';
    }
    if (!empty($this->configuration['treemap_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/treemap';
    }

    $element['#attributes']['class'][] = 'charts-highchart';
    $element['#chart_definition'] = $chart_definition;

    // Show a form on the front-end so users can change chart colors.
    // Only available in standard mode or if explicitly supported in raw mode.
    if (!empty($element['#color_changer']) && empty($element['#in_preview_mode'])) {
      $element = $this->applyColorChanger($element, $chart_definition);
    }

    // Setting global options.
    $element['#attached']['drupalSettings']['charts']['highcharts']['global_options'] = $this->processedGlobalOptions();

    // Add default Highcharts CSS library if global styled mode option
    // is enabled.
    if (!empty($this->configuration['global_options']['chart']['styled_mode']) && empty($this->configuration['disable_default_css_library'])) {
      $element['#attached']['library'][] = 'charts_highcharts/highcharts_default_css';
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
    $form_state_items = [
      'chart_series' => $chart_definition['series'] ?? [],
      'chart_id' => $element['#id'],
      'chart_type' => $chart_definition['chart']['type'] ?? 'line',
    ];
    if (!empty($chart_definition['yAxis'])) {
      $form_state_items['y_axis'] = $chart_definition['yAxis'];
    }
    $element['#attached']['library'][] = 'charts_highcharts/color_changer';
    $element['#content_suffix']['color_changer'] = $this->colorChangerFormBuilder($form_state_items);

    return $element;
  }

  /**
   * Defines the default global options.
   */
  public static function defaultGlobalOptions() {
    return [
      'lang' => [
        'download_CSV' => 'Download CSV',
        'download_JPEG' => 'Download JPEG image',
        'download_PDF' => 'Download PDF document',
        'download_PNG' => 'Download PNG image ',
        'download_SVG' => 'Download SVG vector image',
        'download_XLS' => 'Download XLS',
        'exit_fullscreen' => 'Exit from full screen',
        'hide_data' => 'Hide data table',
        'loading' => 'Loading...',
        'main_breadcrumb' => 'Main',
        'thousands_sep' => ' ',
        'decimal_point' => '.',
        'no_data' => 'No data to display',
        'print_chart' => 'Print chart',
        'reset_zoom' => 'Reset zoom',
        'reset_zoom_title' => 'Reset zoom level 1:1',
        'view_data' => 'View data table',
        'view_fullscreen' => 'View in full screen',
        'context_button_title' => 'Chart context menu',
        'drill_up_text' => 'Back to {series.name}',
        'invalid_date' => 'Invalid date',
        'months' => [
          'January',
          'February',
          'March',
          'April',
          'May',
          'June',
          'July',
          'August',
          'September',
          'October',
          'November',
          'December',
        ],
        'short_months' => [
          'Jan',
          'Feb',
          'Mar',
          'Apr',
          'May',
          'Jun',
          'Jul',
          'Aug',
          'Sept',
          'Oct',
          'Nov',
          'Dec',
        ],
        'weekdays' => [
          'Sunday',
          'Monday',
          'Tuesday',
          'Wednesday',
          'Thursday',
          'Friday',
          'Saturday',
        ],
        'short_weekdays' => [
          'Sun',
          'Mon',
          'Tue',
          'Wed',
          'Thurs',
          'Fri',
          'Sat',
        ],
        'export_data' => [
          'annotation_header' => 'Annotations',
          'category_datetime_header' => 'DateTime',
          'category_header' => 'Category',
        ],
        'numeric_symbols' => [
          'k',
          'M',
          'G',
          'T',
          'P',
          'E',
        ],
      ],
      'chart' => [
        'styledMode' => FALSE,
      ],
    ];
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
  protected function populateOptions(array $element, array $chart_definition) {
    $chart_type = $this->getType($element['#chart_type']);
    $chart_definition['chart']['type'] = $chart_type;
    $chart_definition['chart']['backgroundColor'] = $element['#background'];
    $chart_definition['chart']['polar'] = $element['#polar'] ?? NULL;
    $chart_definition['chart']['options3d']['enabled'] = $element['#three_dimensional'] ?? NULL;
    $chart_definition['credits']['enabled'] = FALSE;
    $chart_definition['title']['text'] = $element['#title'] ?? '';
    $chart_definition['title']['style']['color'] = $element['#title_color'];
    $title_position = $element['#title_position'] ?? '';
    $chart_definition['title']['align'] = in_array($title_position, [
      'left',
      'center',
      'right',
    ]) ? $title_position : NULL;
    $chart_definition['title']['verticalAlign'] = in_array($title_position, [
      'top',
      'bottom',
    ]) ? $title_position : NULL;
    $chart_definition['title']['y'] = $title_position === 'in' ? 24 : NULL;
    $chart_definition['subtitle']['text'] = $element['#subtitle'] ?? '';
    $chart_definition['subtitle']['align'] = in_array($title_position, [
      'left',
      'center',
      'right',
    ]) ? $title_position : NULL;
    $chart_definition['subtitle']['verticalAlign'] = in_array($title_position, [
      'top',
      'bottom',
    ]) ? $title_position : NULL;
    if (!empty($element['#subtitle']) && !empty($title_position)) {
      if ($title_position === 'in') {
        $chart_definition['subtitle']['y'] = 42;
      }
      elseif ($title_position === 'bottom') {
        $chart_definition['subtitle']['y'] = 26;
      }
    }
    $chart_definition['colors'] = $element['#colors'];
    $chart_definition['tooltip']['enabled'] = (bool) $element['#tooltips'];
    $chart_definition['tooltip']['useHTML'] = (bool) $element['#tooltips_use_html'];
    $chart_definition['plotOptions']['series']['stacking'] = $element['#stacking'] ?? '';
    $chart_definition['plotOptions']['series']['dataLabels']['enabled'] = (bool) $element['#data_labels'];
    $chart_definition['plotOptions']['series']['marker']['enabled'] = (bool) $element['#data_markers'];
    $chart_definition['plotOptions']['series']['connectNulls'] = !empty($element['#connect_nulls']);
    // Special handling for dumbbell charts.
    if ($element['#chart_type'] === 'dumbbell') {
      // They need to be inverted (horizontal).
      $chart_definition['chart']['inverted'] = TRUE;
      // They require markers to be visible.
      $chart_definition['plotOptions']['dumbbell']['marker']['enabled'] = TRUE;
    }
    if ($element['#chart_type'] === 'gauge') {
      $chart_definition['yAxis']['plotBands'][] = [
        'from' => (int) $element['#gauge']['red_from'],
        'to' => (int) $element['#gauge']['red_to'],
        'color' => 'red',
      ];
      $chart_definition['yAxis']['plotBands'][] = [
        'from' => (int) $element['#gauge']['yellow_from'],
        'to' => (int) $element['#gauge']['yellow_to'],
        'color' => 'yellow',
      ];
      $chart_definition['yAxis']['plotBands'][] = [
        'from' => (int) $element['#gauge']['green_from'],
        'to' => (int) $element['#gauge']['green_to'],
        'color' => 'green',
      ];
      $chart_definition['yAxis']['min'] = (int) $element['#gauge']['min'];
      $chart_definition['yAxis']['max'] = (int) $element['#gauge']['max'];
    }
    if ($element['#chart_type'] === 'solidgauge') {
      $chart_definition['yAxis']['min'] = (int) $element['#library_type_options']['min'];
      $chart_definition['yAxis']['max'] = (int) $element['#library_type_options']['max'];
      // Loop through the stops and add them to the chart definition.
      $stops = array_values($element['#library_type_options']['stops']);
      foreach (range(0, 3) as $stop_index) {
        $chart_definition['yAxis']['stops'][$stop_index] = [
          (float) $stops[$stop_index]['position'],
          $stops[$stop_index]['color'],
        ];
      }
      $chart_definition['pane'] = [
        'center' => ['50%', '85%'],
        'size' => '140%',
        'startAngle' => -90,
        'endAngle' => 90,
        'background' => [
          'backgroundColor' => '#fafafa',
          'innerRadius' => '60%',
          'outerRadius' => '100%',
          'shape' => 'arc',
        ],
      ];
      $chart_definition['plotOptions']['solidgauge']['dataLabels']['borderWidth'] = 0;
      $chart_definition['plotOptions']['solidgauge']['dataLabels']['y'] = -25;
      $chart_definition['plotOptions']['solidgauge']['dataLabels']['style']['fontSize'] = '24px';
      $chart_definition['plotOptions']['solidgauge']['dataLabels']['color'] = $element['#title_color'];
    }

    if (!empty($element['#library_type_options']['enable_stacklabels'])) {
      $chart_definition['yAxis']['stackLabels']['enabled'] = TRUE;
    }

    // These changes are for consistency with Google. Perhaps too specific?
    if (in_array($element['#chart_type'], ['donut', 'pie'])) {
      $chart_definition['plotOptions']['pie']['dataLabels']['distance'] = -30;
      $chart_definition['plotOptions']['pie']['dataLabels']['color'] = 'white';
      $chart_definition['plotOptions']['pie']['dataLabels']['format'] = '{percentage:.1f}%';

      $chart_definition['tooltip']['pointFormat'] = '<b>{point.y} ({point.percentage:.1f}%)</b><br/>';
    }

    // Check if colorAxis is enabled.
    if (!empty($element['#library_type_options']['coloraxis'])) {
      $chart_definition['colorAxis'] = [
        'minColor' => $element['#library_type_options']['min_color'],
        'maxColor' => $element['#library_type_options']['max_color'],
      ];
    }

    if ($element['#legend'] === TRUE) {
      $chart_definition['legend']['enabled'] = $element['#legend'];
      if (in_array($element['#chart_type'], ['pie', 'donut'])) {
        $chart_definition['plotOptions']['pie']['showInLegend'] = TRUE;
      }
      elseif ($element['#chart_type'] == 'gauge') {
        $chart_definition['plotOptions']['gauge']['showInLegend'] = TRUE;
      }
      if (!empty($element['#legend_title'])) {
        $chart_definition['legend']['title']['text'] = $element['#legend_title'];
      }

      if ($element['#legend_position'] === 'bottom') {
        $chart_definition['legend']['verticalAlign'] = 'bottom';
        $chart_definition['legend']['layout'] = 'horizontal';
      }
      elseif ($element['#legend_position'] === 'top') {
        $chart_definition['legend']['verticalAlign'] = 'top';
        $chart_definition['legend']['layout'] = 'horizontal';
      }
      else {
        $chart_definition['legend']['align'] = $element['#legend_position'];
        $chart_definition['legend']['verticalAlign'] = 'middle';
        $chart_definition['legend']['layout'] = 'vertical';
      }

      // Setting more legend configuration based on the plugin form entry.
      $legend_configuration = $this->configuration['legend'] ?? [];
      if (!empty($legend_configuration['layout'])) {
        $chart_definition['legend']['layout'] = $legend_configuration['layout'];
      }
      if (!empty($legend_configuration['background_color'])) {
        $chart_definition['legend']['backgroundColor'] = $legend_configuration['background_color'];
      }
      if (!empty($legend_configuration['border_width'])) {
        $chart_definition['legend']['borderWidth'] = $legend_configuration['border_width'];
      }
      if (!empty($legend_configuration['shadow'])) {
        $chart_definition['legend']['shadow'] = TRUE;
      }
      if (!empty($legend_configuration['item_style']['color'])) {
        $chart_definition['legend']['itemStyle']['color'] = $legend_configuration['item_style']['color'];
      }
      if (!empty($legend_configuration['item_style']['overflow'])) {
        $chart_definition['legend']['itemStyle']['overflow'] = $legend_configuration['item_style']['overflow'];
      }
    }
    else {
      $chart_definition['legend']['enabled'] = FALSE;
    }

    // Merge in chart raw options and return the definition.
    return $this->applyRawOptions($element, $chart_definition);
  }

  /**
   * Utility to populate data.
   *
   * @param array $element
   *   The element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   Return the chart definition.
   */
  protected function populateData(array &$element, array $chart_definition): array {
    $categories = [];
    $chart_type = $this->getType($element['#chart_type']);

    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_xaxis' && !empty($element[$key]['#labels'])) {
        if (in_array($chart_type, ['pie', 'treemap'])) {
          $categories = $element[$key]['#labels'];
          break;
        }
        $categories[] = $element[$key]['#labels'];
      }
    }
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_data') {
        $series = [];
        $series_data = [];

        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Convert target named axis keys to integers.
        if (isset($element[$key]['#target_axis'])) {
          $axis_name = $element[$key]['#target_axis'];
          $axis_index = 0;
          foreach (Element::children($element) as $axis_key) {
            if ($element[$axis_key]['#type'] === 'chart_yaxis') {
              if ($axis_key === $axis_name) {
                break;
              }
              $axis_index++;
            }
          }
          $series['yAxis'] = $axis_index;
        }

        // Allow data to provide the labels.
        // This will override the axis settings.
        if ($element[$key]['#labels'] && !in_array($element[$key]['#chart_type'], [
          'scatter',
          'bubble',
        ])) {
          foreach ($element[$key]['#labels'] as $label_index => $label) {
            $series_data[$label_index][0] = $label;
          }
        }
        elseif (!empty($categories) && (in_array($chart_type, ['pie', 'treemap']))) {
          foreach ($categories as $label_index => $label) {
            $series_data[$label_index][0] = $label;
          }
        }

        // Populate the data.
        foreach ($element[$key]['#data'] as $data_index => $data) {
          if (isset($series_data[$data_index])) {
            $series_data[$data_index][] = $data;
            if ($chart_type === 'treemap') {
              // Add the colorValue value.
              $series_data[$data_index][] = $data;
            }
          }
          elseif ($chart_type === 'pie') {
            $series_data[$data_index] = $data;
            $name = $series_data[$data_index]['name'] ?? NULL;
            if (!empty($element[$key]['#grouping_colors'][$data_index][$name])) {
              $series_data[$data_index]['color'] = $element[$key]['#grouping_colors'][$data_index][$name];
            }
            elseif (!empty($element[$key]['#grouping_colors'][$data_index]) && is_array($element[$key]['#grouping_colors'][$data_index])) {
              $chart_definition['colors'][$data_index] = reset($element[$key]['#grouping_colors'][$data_index]);
            }
          }
          else {
            if ($chart_type === 'dumbbell') {
              $series_data[$data_index]['lowColor'] = $element['#library_type_options']['low_color'];
              $series_data[$data_index]['low'] = $data[0];
              $series_data[$data_index]['high'] = $data[1];
            }
            else {
              $series_data[$data_index] = $data;
            }
          }
        }

        $series['type'] = $element[$key]['#chart_type'];
        if ($element['#chart_type'] === 'donut') {
          // Add innerSize to differentiate between donut and pie.
          $series['innerSize'] = '40%';
        }
        $series['name'] = $element[$key]['#title'];
        $series['color'] = $element[$key]['#color'];

        if ($element[$key]['#prefix'] || $element[$key]['#suffix']) {
          $yaxis_index = $series['yAxis'] ?? 0;
          // For axis formatting, we need to use a format string.
          // See http://docs.highcharts.com/#formatting.
          $decimal_formatting = $element[$key]['#decimal_count'] ? (':.' . $element[$key]['#decimal_count'] . 'f') : '';
          $chart_definition['yAxis'][$yaxis_index]['labels']['format'] = $element[$key]['#prefix'] . "{value$decimal_formatting}" . $element[$key]['#suffix'];
        }

        // Remove unnecessary keys to trim down the resulting JS settings.
        ChartElement::trimArray($series);

        // If you want a different type of scatter.
        if (!empty($element['#alternative_scatter'])) {
          $series = $series_data;
        }
        else {
          $series['data'] = $series_data;
        }

        // Merge in series raw options.
        $series = $this->applyRawOptions($element[$key], $series);

        // Add the series to the main chart definition.
        // Scatter colors adjustment.
        if (!empty($element['#alternative_scatter'])) {
          $chart_definition['series'] = $series;
        }
        else {
          $chart_definition['series'][$key] = $series;
        }

        // Merge in any point-specific data points.
        foreach (Element::children($element[$key]) as $sub_key) {
          if ($element[$key][$sub_key]['#type'] === 'chart_data_item') {
            // Make sure defaults are loaded.
            if (empty($element[$key][$sub_key]['#defaults_loaded'])) {
              $element[$key][$sub_key] += $this->elementInfo->getInfo($element[$key][$sub_key]['#type']);
            }

            $data_item = $element[$key][$sub_key];
            $series_point = &$chart_definition['series'][$key]['data'][$sub_key];

            // Convert the point from a simple data value to a complex point.
            if (!isset($series_point['data'])) {
              $data = $series_point;
              $series_point = [];
              if (is_array($data)) {
                $series_point['name'] = $data[0];
                $series_point['y'] = $data[1];
              }
              else {
                $series_point['y'] = $data;
              }
            }
            if (isset($data_item['#data'])) {
              if (is_array($data_item['#data'])) {
                $series_point['x'] = $data_item['#data'][0];
                $series_point['y'] = $data_item['#data'][1];
              }
              else {
                $series_point['y'] = $data_item['#data'];
              }
            }
            if ($data_item['#title']) {
              $series_point['name'] = $data_item['#title'];
            }

            // Setting the color requires several properties for consistency.
            $series_point['color'] = $data_item['#color'];
            $series_point['fillColor'] = $data_item['#color'];
            $series_point['states']['hover']['fillColor'] = $data_item['#color'];
            $series_point['states']['select']['fillColor'] = $data_item['#color'];
            ChartElement::trimArray($series_point);

            // Merge in point raw options.
            $series_point = $this->applyRawOptions($data_item, $series_point);
          }
        }
      }
    }

    // Pareto options.
    if (!empty($element['#library_type_options']['pareto_line'])) {
      $chart_definition = $this->applyParetoOptions($element, $chart_definition);
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
  protected function populateAxes(array $element, array $chart_definition) {
    foreach (Element::children($element) as $key) {
      if ($element[$key]['#type'] === 'chart_xaxis' || $element[$key]['#type'] === 'chart_yaxis') {
        // Make sure defaults are loaded.
        if (empty($element[$key]['#defaults_loaded'])) {
          $element[$key] += $this->elementInfo->getInfo($element[$key]['#type']);
        }

        // Populate the chart data.
        $axis_type = $element[$key]['#type'] === 'chart_xaxis' ? 'xAxis' : 'yAxis';
        $axis = [];
        $axis['type'] = $element[$key]['#axis_type'];
        $axis['title']['text'] = $element[$key]['#title'];
        $axis['title']['style']['color'] = $element[$key]['#title_color'];
        if (!empty($element[$key]['#labels'])) {
          $axis['categories'] = $element[$key]['#labels'];
        }
        $axis['labels']['style']['color'] = $element[$key]['#labels_color'];
        $axis['labels']['rotation'] = $element[$key]['#labels_rotation'];
        $axis['gridLineColor'] = $element[$key]['#grid_line_color'];
        $axis['lineColor'] = $element[$key]['#base_line_color'];
        $axis['minorGridLineColor'] = $element[$key]['#minor_grid_line_color'];
        $axis['endOnTick'] = isset($element[$key]['#max']) ? FALSE : NULL;
        $axis['max'] = $element[$key]['#max'];
        $axis['min'] = $element[$key]['#min'];
        $axis['opposite'] = $element[$key]['#opposite'];

        if ($axis['labels']['rotation']) {
          $chart_type = $this->chartTypeManager->getDefinition($element['#chart_type']);
          if ($axis_type === 'xAxis' && !$chart_type['axis_inverted']) {
            $axis['labels']['align'] = 'left';
          }
          elseif ($axis_type === 'yAxis' && $chart_type['axis_inverted']) {
            $axis['labels']['align'] = 'left';
          }
        }

        // Merge in axis raw options.
        $axis = $this->applyRawOptions($element[$key], $axis);

        $chart_definition[$axis_type][] = $axis;
      }
    }

    return $chart_definition;
  }

  /**
   * The chart type.
   *
   * @param string $type
   *   The chart type.
   *
   * @return string
   *   Return the chart type.
   */
  protected function getType($type) {
    return $type === 'donut' ? 'pie' : $type;
  }

  /**
   * Process definition for Pareto charts.
   *
   * @param array $element
   *   The chart element.
   * @param array $chart_definition
   *   The chart definition.
   *
   * @return array
   *   The updated chart definition.
   */
  private function applyParetoOptions(array $element, array $chart_definition) {
    // Move stackLabels from the root of yAxis to the first axis object.
    if (isset($chart_definition['yAxis']['stackLabels'])) {
      $stack_labels = $chart_definition['yAxis']['stackLabels'];
      unset($chart_definition['yAxis']['stackLabels']);
      // Ensure index 0 exists to receive the labels.
      if (!isset($chart_definition['yAxis'][0])) {
        $chart_definition['yAxis'][0] = [];
      }
      $chart_definition['yAxis'][0]['stackLabels'] = $stack_labels;
    }

    // Normalize yAxis into a sequential array.
    if (isset($chart_definition['yAxis'])) {
      // array_values() strips string keys and closes gaps in numeric keys.
      $chart_definition['yAxis'] = array_values($chart_definition['yAxis']);
    }

    // Handle Pareto line injection.
    $series_keys = array_keys($chart_definition['series'] ?? []);
    $first_key = reset($series_keys);

    // Return if first_key is false or not set.
    if ($first_key === FALSE || !isset($chart_definition['series'][$first_key])) {
      return $chart_definition;
    }

    $chart_definition['series'][$first_key]['id'] = 'pareto_base';

    // Check if an appropriate secondary axis already exists.
    $pareto_axis_index = NULL;
    foreach ($chart_definition['yAxis'] as $index => $axis) {
      if (!empty($axis['opposite']) && (isset($axis['max']) && $axis['max'] == 100)) {
        $pareto_axis_index = $index;
        break;
      }
    }

    // Create the axis if not found.
    if ($pareto_axis_index === NULL) {
      $pareto_axis_index = count($chart_definition['yAxis']);
      $chart_definition['yAxis'][$pareto_axis_index] = [
        'title' => [
          'text' => $this->t('Percentage'),
        ],
        'opposite' => TRUE,
        'min' => 0,
        'max' => 100,
        'labels' => [
          'format' => '{value:.2f}%',
        ],
      ];
    }

    // Inject the Pareto series.
    $chart_definition['series'][] = [
      'type' => 'pareto',
      'name' => $this->t('Cumulative %'),
      'baseSeries' => 'pareto_base',
      'yAxis' => $pareto_axis_index,
      'color' => $element['#library_type_options']['pareto_color'] ?? '#000000',
      'zIndex' => 10,
      'tooltip' => [
        'pointFormat' => '<span style="color:{point.color}">●</span> {series.name}: <b>{point.y:.2f}%</b><br/>',
      ],
      'dataLabels' => [
        'enabled' => (bool) $element['#data_labels'],
        'format' => '{point.y:.2f}%',
      ],
    ];

    return $chart_definition;
  }

  /**
   * Defines data for the config form.
   */
  private function datesDataForConfigForm() {
    $month = [
      'label_singular' => 'Month',
      'label_plural' => $this->t('Months'),
      'description' => $this->t('The full month names.'),
      'range_end' => 11,
    ];
    $weekday = [
      'label_singular' => 'Weekday',
      'label_plural' => $this->t('Weekdays'),
      'description' => $this->t('The weekday names, starting Sunday.'),
      'range_end' => 6,
    ];
    return [
      'months' => $month,
      'short_months' => [
        'label_plural' => $this->t('Short Months'),
        'label_singular' => 'Short Month',
        'description' => $this->t('The months names in abbreviated form. E.g. Jan, Feb, etc.'),
      ] + $month,
      'weekdays' => $weekday,
      'short_weekdays' => [
        'label_plural' => $this->t('Short Weekdays'),
        'label_singular' => 'Short Weekday',
        'description' => $this->t('Short week days, starting Sunday. E.g. Sun, Mon, etc.'),
      ] + $weekday,
    ];
  }

  /**
   * Defines data for export data options.
   */
  private function exportDataForConfigForm() {
    return [
      'annotation_header' => [
        'label' => 'Annotation header',
        'description' => 'The annotation column title.',
      ],
      'category_datetime_header' => [
        'label' => 'Category datetime header',
        'description' => 'The category column title when axis type set to "datetime"',
      ],
      'category_header' => [
        'label' => 'Category Header',
        'description' => 'The category column title.',
      ],
    ];
  }

  /**
   * Returns the transformed global options.
   */
  private function processedGlobalOptions() {
    $global_options = $this->configuration['global_options'] ?? ['lang' => []];
    $global_options['lang'] += static::defaultGlobalOptions()['lang'];
    $language_options = &$global_options['lang'];
    foreach ($language_options as $option_key => $value) {
      if (strpos($option_key, 'download_') === 0) {
        $transformed_key = str_replace('_', '', $option_key);
      }
      else {
        $transformed_key = $this->transformSnakeCaseToCamelCase($option_key);
        if ($option_key === 'export_data' && is_array($value)) {
          foreach ($value as $export_data_key => $export_data_value) {
            unset($value[$export_data_key]);
            $export_data_key = $this->transformSnakeCaseToCamelCase($export_data_key);
            $value[$export_data_key] = $export_data_value;
          }
        }
      }
      if ($transformed_key === $option_key) {
        continue;
      }

      $language_options[$transformed_key] = $value;
      unset($language_options[$option_key]);
    }
    // Add global chart options, such as styledMode for dark mode support.
    $global_options['chart'] = $global_options['chart'] ?? [];
    $global_options['chart'] += static::defaultGlobalOptions()['chart'];
    foreach ($global_options['chart'] as $option_key => $value) {
      $transformed_key = $this->transformSnakeCaseToCamelCase($option_key);
      // Use boolean value for styledMode option.
      if ($option_key === 'styled_mode') {
        $value = (bool) $value;
      }
      $global_options['chart'][$transformed_key] = $value;
    }
    return $global_options;
  }

  /**
   * Transform the string from snakeCase to CamelCase.
   */
  private function transformSnakeCaseToCamelCase(string $input) {
    $separator = '_';
    $input = strtolower($input);
    if (strpos($input, $separator) === FALSE) {
      return $input;
    }
    return lcfirst(str_replace($separator, '', ucwords($input, $separator)));
  }

}
