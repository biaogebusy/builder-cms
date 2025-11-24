<?php

namespace Drupal\charts\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\charts\ColorHelperTrait;
use Drupal\views\Views;

/**
 * Provides a form element for setting a chart.
 *
 * Properties:
 * - #used_in: Where the form is being used. basic_form is the default and other
 *   supported values are config_form for the main chart setting form and
 *   view_form for the view field form.
 * - #series: A boolean value. Set to TRUE when the usage require to collect
 *   chart series data.
 * - #field_options: properties mostly used by the view_form.
 *
 * Usage example:
 *
 * @code
 * $form['chart_config'] = [
 *   '#type' => 'charts_settings',
 *   '#title' => 'Charts configurations',
 *   '#used_in' => 'basic_form',
 * ];
 * @endcode
 *
 * @FormElement("charts_settings")
 */
class BaseSettings extends FormElementBase {

  use ColorHelperTrait;
  use ElementFormStateTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#tree' => TRUE,
      '#default_value' => [],
      '#used_in' => 'basic_form',
      '#series' => FALSE,
      '#required' => FALSE,
      '#field_options' => [],
      '#library' => '',
      '#process' => [
        [$class, 'attachLibraryElementSubmit'],
        [$class, 'processSettings'],
        [$class, 'processGroup'],
      ],
      '#element_validate' => [
        [$class, 'validateLibraryPluginConfiguration'],
      ],
      '#charts_library_settings_element_submit' => [
        [$class, 'submitLibraryPluginConfiguration'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Processes the settings element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The element.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function processSettings(array &$element, FormStateInterface $form_state, array &$complete_form = []) {
    $supported_usage = ['basic_form', 'config_form', 'view_form'];
    if (empty($element['#used_in']) || !in_array($element['#used_in'], $supported_usage)) {
      throw new \InvalidArgumentException('The chart_base_settings element can only be used in basic, config and view forms.');
    }
    if (!is_array($element['#value'])) {
      throw new \InvalidArgumentException('The chart_base_settings #default_value must be an array.');
    }
    $parents = $element['#parents'];
    $id_prefix = implode('-', $parents);
    $wrapper_id = Html::getUniqueId($id_prefix . '-ajax-wrapper');
    $value = $element['#value'];

    // Collect the main prefix and suffix just in case this element is wrapped
    // with one.
    $main_prefix = $element['#prefix'] ?? '';
    $main_suffix = $element['#suffix'] ?? '';

    // Enforce tree.
    $element = [
      '#tree' => TRUE,
      '#prefix' => $main_prefix . '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>' . $main_suffix,
      // Pass the id along to other methods.
      '#wrapper_id' => $wrapper_id,
    ] + $element;
    $used_in = $element['#used_in'] ?: '';

    $required = !empty($element['#required']) ? $element['#required'] : FALSE;
    $options = $value;

    $library_options = self::getLibraries();
    if ($used_in !== 'config_form' && $library_options) {
      $library_options = [
        'site_default' => new TranslatableMarkup('Site Default'),
      ] + $library_options;
    }
    if (!$library_options) {
      $element['no_chart_plugin_error'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--error'],
        ],
        'content' => [
          '#markup' => new TranslatableMarkup('<p>Please <a href="/admin/modules">install</a> at least one module that implements a chart library plugin.</p>'),
        ],
      ];
      return $element;
    }

    if (!empty($element['#library']) && isset($library_options[$element['#library']])) {
      $element['library'] = [
        '#type' => 'hidden',
        '#value' => $element['#library'],
      ];
      $selected_library = $element['#library'];
    }
    else {
      $element['library'] = [
        '#title' => new TranslatableMarkup('Charting library'),
        '#type' => 'select',
        '#options' => $library_options,
        '#default_value' => $options['library'],
        '#required' => $required,
        '#access' => count($library_options) > 0,
        '#attributes' => ['class' => ['chart-library-select']],
        '#ajax' => [
          'callback' => [get_called_class(), 'ajaxRefresh'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $selected_library = $options['library'] ?: array_key_first($library_options);
    }

    // Making sure that the selected chart type is part of chart type options
    // associated with the library.
    $chart_type_options = self::getChartTypes($selected_library);
    $selected_chart_type = $options['type'] ?? '';
    if (!isset($chart_type_options[$selected_chart_type])) {
      $selected_chart_type = NULL;
    }
    $element['type'] = [
      '#title' => new TranslatableMarkup('Chart type'),
      '#type' => 'radios',
      '#default_value' => $selected_chart_type,
      '#options' => $chart_type_options,
      '#access' => !empty($chart_type_options),
      '#required' => $required,
      '#attributes' => [
        'class' => [
          'chart-type-radios',
          'container-inline',
        ],
      ],
      '#value' => $selected_chart_type,
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
      ],
    ];

    if (!$chart_type_options) {
      $error_message = $selected_library !== 'site_default' ?
        new TranslatableMarkup('<p>The selected chart library does not have valid chart type options. Please select another charting library or install a module that have chart type options.</p>') :
        new TranslatableMarkup('<p>The site default charting library has not been set yet, or it does not support chart type options. Please ensure that you have correctly <a href="/admin/config/content/charts">configured the chart module</a>.</p>');
      $element['no_chart_type_plugin_error'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--error']],
        'content' => ['#markup' => $error_message],
      ];
      return $element;
    }

    // Adding settings related to the library and the selected chart type.
    $element = static::addLibraryElementOptions($element, $form_state, $complete_form);

    if (!empty($element['#series'])) {
      $element = self::processSeriesForm($element, $options, $form_state);
    }

    $element['display'] = [
      '#title' => new TranslatableMarkup('Display'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $element['display']['title'] = [
      '#title' => new TranslatableMarkup('Chart title'),
      '#type' => 'textfield',
      '#default_value' => $options['display']['title'],
    ];

    $element['display']['subtitle'] = [
      '#title' => new TranslatableMarkup('Chart subtitle'),
      '#type' => 'textfield',
      '#default_value' => $options['display']['subtitle'] ?? '',
      '#description' => new TranslatableMarkup('Not all charting libraries support this option. Disabled until there is a title value.'),
      '#states' => [
        'disabled' => [
          ':input[name="' . $element['#name'] . '[display][title]"]' => ['value' => ''],
        ],
      ],
    ];

    $element['xaxis'] = [
      '#title' => new TranslatableMarkup('Horizontal axis'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#attributes' => ['class' => ['chart-xaxis']],
    ];

    $element['yaxis'] = [
      '#title' => new TranslatableMarkup('Vertical axis'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#attributes' => ['class' => ['chart-yaxis']],
    ];

    if ($used_in === 'view_form') {
      $element = self::processViewForm($element, $options, $complete_form, $form_state);
    }
    elseif ($used_in === 'config_form') {
      $element = self::processConfigForm($element, $options, $form_state);
    }

    $element['display']['title_position'] = [
      '#title' => new TranslatableMarkup('Title position'),
      '#type' => 'select',
      '#options' => [
        '' => new TranslatableMarkup('None'),
        'out' => new TranslatableMarkup('Outside'),
        'in' => new TranslatableMarkup('Inside'),
        'top' => new TranslatableMarkup('Top'),
        'right' => new TranslatableMarkup('Right'),
        'bottom' => new TranslatableMarkup('Bottom'),
        'left' => new TranslatableMarkup('Left'),
      ],
      '#description' => new TranslatableMarkup('Not all of these will apply to your selected library.'),
      '#default_value' => $options['display']['title_position'] ?? '',
    ];

    $element['display']['tooltips'] = [
      '#title' => new TranslatableMarkup('Enable tooltips'),
      '#type' => 'checkbox',
      '#description' => new TranslatableMarkup('Show data details on mouse over? Note: unavailable for print or on mobile devices.'),
      '#default_value' => !empty($options['display']['tooltips']),
    ];

    $element['display']['data_labels'] = [
      '#title' => new TranslatableMarkup('Enable data labels'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['display']['data_labels']),
      '#description' => new TranslatableMarkup('Show data details as labels on chart? Note: recommended for print or on mobile devices.'),
    ];

    $element['display']['data_markers'] = [
      '#title' => new TranslatableMarkup('Enable data markers'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['display']['data_markers']),
      '#description' => new TranslatableMarkup('Show data markers (points) on line charts?'),
    ];

    $element['display']['connect_nulls'] = [
      '#title' => new TranslatableMarkup('Connect nulls'),
      '#type' => 'checkbox',
      '#default_value' => !empty($options['display']['connect_nulls']),
      '#description' => new TranslatableMarkup('Connect interrupted points on line charts?'),
    ];

    $element['display']['legend_position'] = [
      '#title' => new TranslatableMarkup('Legend position'),
      '#type' => 'select',
      '#options' => [
        '' => new TranslatableMarkup('None'),
        'top' => new TranslatableMarkup('Top'),
        'right' => new TranslatableMarkup('Right'),
        'bottom' => new TranslatableMarkup('Bottom'),
        'left' => new TranslatableMarkup('Left'),
      ],
      '#default_value' => $options['display']['legend_position'] ?? '',
    ];

    $element['display']['background'] = [
      '#title' => new TranslatableMarkup('Background color'),
      '#type' => 'textfield',
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => ['placeholder' => new TranslatableMarkup('transparent')],
      '#description' => new TranslatableMarkup('Leave blank for a transparent background.'),
      '#default_value' => $options['display']['background'] ?? '',
    ];

    $element['display']['three_dimensional'] = [
      '#title' => new TranslatableMarkup('Make chart three-dimensional (3D)'),
      '#type' => 'checkbox',
      '#default_value' => $options['display']['three_dimensional'] ?? FALSE,
      '#attributes' => [
        'class' => [
          'chart-type-checkbox',
          'container-inline',
        ],
      ],
    ];

    $element['display']['polar'] = [
      '#title' => new TranslatableMarkup('Transform cartesian charts into the polar coordinate system'),
      '#type' => 'checkbox',
      '#default_value' => $options['display']['polar'] ?? FALSE,
      '#attributes' => [
        'class' => [
          'chart-type-checkbox',
          'container-inline',
        ],
      ],
    ];

    $element['display']['dimensions'] = [
      '#title' => new TranslatableMarkup('Dimensions'),
      '#theme_wrappers' => ['form_element'],
      '#description' => new TranslatableMarkup('If dimensions are left empty, the chart will fill its containing element.'),
    ];

    $element['display']['dimensions']['width'] = [
      '#type' => 'number',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('auto'),
      ],
      '#min' => 0,
      '#max' => 9999,
      '#default_value' => $options['display']['dimensions']['width'] ?? '',
      '#size' => 8,
      '#theme_wrappers' => [],
    ];
    $element['display']['dimensions']['width_units'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('%'),
      ],
      '#default_value' => $options['display']['dimensions']['width_units'] ?? '',
      '#suffix' => ' x ',
      '#size' => 2,
      '#theme_wrappers' => [],
    ];

    $element['display']['dimensions']['height'] = [
      '#type' => 'number',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('auto'),
      ],
      '#min' => 0,
      '#max' => 9999,
      '#default_value' => $options['display']['dimensions']['height'] ?? '',
      '#size' => 8,
      '#theme_wrappers' => [],
    ];
    $element['display']['dimensions']['height_units'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('px'),
      ],
      '#default_value' => $options['display']['dimensions']['height_units'] ?? '',
      '#size' => 2,
      '#theme_wrappers' => [],
    ];

    $element['xaxis']['title'] = [
      '#title' => new TranslatableMarkup('Custom title'),
      '#type' => 'textfield',
      '#default_value' => $options['xaxis']['title'] ?? '',
    ];

    $element['xaxis']['labels_rotation'] = [
      '#title' => new TranslatableMarkup('Labels rotation'),
      '#type' => 'select',
      '#options' => [
        0 => new TranslatableMarkup('0°'),
        15 => new TranslatableMarkup('15°'),
        30 => new TranslatableMarkup('30°'),
        45 => new TranslatableMarkup('45°'),
        60 => new TranslatableMarkup('60°'),
        90 => new TranslatableMarkup('90°'),
      ],
      // This is only shown on non-inverted charts.
      '#attributes' => ['class' => ['axis-inverted-hide']],
      '#default_value' => $options['xaxis']['labels_rotation'] ?? '',
    ];

    $element['yaxis']['title'] = [
      '#title' => new TranslatableMarkup('Custom title'),
      '#type' => 'textfield',
      '#default_value' => $options['yaxis']['title'] ?? '',
    ];

    $element['yaxis']['min_max_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => new TranslatableMarkup('Value range'),
    ];
    $element['yaxis']['min'] = [
      '#type' => 'number',
      '#title' => new TranslatableMarkup('Value range minimum'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('Minimum'),
      ],
      '#max' => 999999999,
      '#default_value' => $options['yaxis']['min'] ?? '',
      '#size' => 12,
      '#suffix' => ' ',
    ];
    $element['yaxis']['max'] = [
      '#type' => 'number',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('Maximum'),
      ],
      '#max' => 999999999,
      '#default_value' => $options['yaxis']['max'] ?? '',
      '#size' => 12,
    ];

    $element['yaxis']['prefix'] = [
      '#title' => new TranslatableMarkup('Value prefix'),
      '#type' => 'textfield',
      '#default_value' => $options['yaxis']['prefix'] ?? '',
      '#size' => 12,
    ];
    $element['yaxis']['suffix'] = [
      '#title' => new TranslatableMarkup('Value suffix'),
      '#type' => 'textfield',
      '#default_value' => $options['yaxis']['suffix'] ?? '',
      '#size' => 12,
    ];

    $element['yaxis']['decimal_count'] = [
      '#title' => new TranslatableMarkup('Decimal count'),
      '#type' => 'number',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('auto'),
      ],
      '#min' => 0,
      '#max' => 20,
      '#default_value' => $options['yaxis']['decimal_count'] ?? '',
      '#size' => 5,
      '#description' => new TranslatableMarkup('Enforce a certain number of decimal-place digits in displayed values.'),
    ];

    $element['yaxis']['labels_rotation'] = [
      '#title' => new TranslatableMarkup('Labels rotation'),
      '#type' => 'select',
      '#options' => [
        0 => new TranslatableMarkup('0°'),
        15 => new TranslatableMarkup('15°'),
        30 => new TranslatableMarkup('30°'),
        45 => new TranslatableMarkup('45°'),
        60 => new TranslatableMarkup('60°'),
        90 => new TranslatableMarkup('90°'),
      ],
      // This is only shown on inverted charts.
      '#attributes' => ['class' => ['axis-inverted-show']],
      '#default_value' => $options['yaxis']['labels_rotation'] ?? '',
    ];

    // Adding basic form yaxis other fields.
    if ($used_in === 'basic_form') {
      $element = self::processBasicForm($element, $options);
    }
    if ($used_in === 'view_form' || $used_in === 'basic_form') {
      $element['display']['color_changer'] = [
        '#title' => new TranslatableMarkup('Enable color changer widget'),
        '#type' => 'checkbox',
        '#description' => new TranslatableMarkup('Display a widget that enables users to switch the chart colors.'),
        '#default_value' => !empty($options['display']['color_changer']),
      ];
    }

    // Settings for gauges.
    $element['display']['gauge'] = [
      '#title' => new TranslatableMarkup('Gauge settings'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[class*=chart-type-radios]' => ['value' => 'gauge'],
        ],
      ],
      'max' => [
        '#title' => new TranslatableMarkup('Gauge maximum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['max'] ?? '',
      ],
      'min' => [
        '#title' => new TranslatableMarkup('Gauge minimum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['min'] ?? '',
      ],
      'green_from' => [
        '#title' => new TranslatableMarkup('Green minimum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['green_from'] ?? '',
      ],
      'green_to' => [
        '#title' => new TranslatableMarkup('Green maximum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['green_to'] ?? '',
      ],
      'yellow_from' => [
        '#title' => new TranslatableMarkup('Yellow minimum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['yellow_from'] ?? '',
      ],
      'yellow_to' => [
        '#title' => new TranslatableMarkup('Yellow maximum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['yellow_to'] ?? '',
      ],
      'red_from' => [
        '#title' => new TranslatableMarkup('Red minimum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['red_from'] ?? '',
      ],
      'red_to' => [
        '#title' => new TranslatableMarkup('Red maximum value'),
        '#type' => 'number',
        '#default_value' => $options['display']['gauge']['red_to'] ?? '',
      ],
    ];

    if ($used_in === 'config_form' && $selected_library) {
      $element = self::buildLibraryConfigurationForm($element, $form_state, $selected_library);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE || $input === NULL) {
      return parent::valueCallback($element, $input, $form_state);
    }

    $library = $input['library'] ?? '';
    // Making sure that the selected chart type is part of chart type options
    // associated with the library.
    $chart_type_options = self::getChartTypes($library);
    $chart_type = $input['type'] ?? '';
    if (!isset($chart_type_options[$chart_type])) {
      $chart_type = array_key_first($chart_type_options);
      $input['type'] = $chart_type;
    }
    return $input;
  }

  /**
   * Validates the chart library plugin configuration.
   *
   * @param array $element
   *   The chart base settings element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function validateLibraryPluginConfiguration(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $used_in = $element['#used_in'];
    if ($used_in === 'config_form') {
      $settings = $form_state->getValue($element['#parents']);
      // Adding validate callback for the chart library settings.
      if (!empty($settings['library'])) {
        /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
        $plugin_manager = \Drupal::service('plugin.manager.charts');
        /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
        $plugin = $plugin_manager->createInstance($settings['library']);
        $plugin->validateConfigurationForm($element['library_config'], $form_state);
      }
    }
  }

  /**
   * Submits the plugin configuration.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function submitLibraryPluginConfiguration(array &$element, FormStateInterface $form_state) {
    $used_in = $element['#used_in'];
    if ($used_in === 'config_form') {
      $settings = $form_state->getValue($element['#parents']);
      if (!empty($settings['library'])) {
        /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
        $plugin_manager = \Drupal::service('plugin.manager.charts');
        /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
        $plugin = $plugin_manager->createInstance($settings['library']);
        $plugin->submitConfigurationForm($element['library_config'], $form_state);
        $form_state->setValueForElement($element['library_config'], $plugin->getConfiguration());
      }
    }
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $length = -1;
    if ($triggering_element['#type'] == 'radio') {
      $length = -2;
    }
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, $length));
  }

  /**
   * Get the libraries.
   *
   * @return array
   *   The library options.
   */
  public static function getLibraries() {
    // Using plugins to get the available installed libraries.
    $plugin_manager = \Drupal::service('plugin.manager.charts');
    $plugin_definitions = $plugin_manager->getDefinitions();
    $library_options = [];

    foreach ($plugin_definitions as $plugin_definition) {
      $library_options[$plugin_definition['id']] = $plugin_definition['name'];
    }

    return $library_options;
  }

  /**
   * Gets chart types by chart library.
   *
   * @param string $library_plugin_id
   *   The library.
   *
   * @return array
   *   The type options.
   */
  public static function getChartTypes(string $library_plugin_id = ''): array {
    if ($library_plugin_id === 'site_default') {
      $library_plugin_id = static::getConfiguredSiteDefaultLibraryId();
    }
    if (!$library_plugin_id) {
      \Drupal::logger('charts')->error('Update your custom code to pass the library in getChartTypes() or install a module that implement a charting library plugin.');
      return [];
    }

    $chart_type_plugin_manager = \Drupal::service('plugin.manager.charts_type');
    $chart_plugin_manager = \Drupal::service('plugin.manager.charts');

    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $chart_plugin */
    $chart_plugin = $chart_plugin_manager->createInstance($library_plugin_id, []);
    $chart_type_plugin_definitions = $chart_type_plugin_manager->getDefinitions();
    $types_options = [];

    foreach ($chart_type_plugin_definitions as $plugin_definition) {
      $chart_type_id = $plugin_definition['id'];
      if ($chart_plugin->isSupportedChartType($chart_type_id)) {
        $types_options[$chart_type_id] = $plugin_definition['label'];
      }
    }
    return $types_options;
  }

  /**
   * Gets the configured site default library chart plugin id.
   *
   * @return string
   *   The plugin id or an empty string if nothing has been configured.
   */
  public static function getConfiguredSiteDefaultLibraryId(): string {
    $chart_config = \Drupal::config('charts.settings');
    return $chart_config->get('charts_default_settings.library') ?? '';
  }

  /**
   * Attaches the #charts_library_settings_element_submit functionality.
   *
   * @param array $element
   *   The form element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed form element.
   */
  public static function attachLibraryElementSubmit(array $element, FormStateInterface $form_state, array &$complete_form) {
    if (isset($complete_form['#charts_library_settings_element_submit_attached'])) {
      return $element;
    }
    // The #validate callbacks of the complete form run last.
    // That allows executeElementSubmitHandlers() to be completely certain that
    // the form has passed validation before proceeding.
    $complete_form['#validate'][] = [
      static::class,
      'executeLibraryElementSubmitHandlers',
    ];
    $complete_form['#charts_library_settings_element_submit_attached'] = TRUE;

    return $element;
  }

  /**
   * Submits elements by calling their #charts_library_settings_element_submit.
   *
   * Callbacks.
   *
   * This approach was took from the commerce module to work around the fact.
   * that drupal core doesn't have an element_submit property.
   *
   * @param array &$form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function executeLibraryElementSubmitHandlers(array &$form, FormStateInterface $form_state) {
    if (!$form_state->isSubmitted() || $form_state->hasAnyErrors()) {
      // The form wasn't submitted (#ajax in progress) or failed validation.
      return;
    }
    $triggering_element = $form_state->getTriggeringElement();
    $button_type = $triggering_element['#button_type'] ?? '';
    if ($button_type != 'primary' && count($form_state->getButtons()) > 1) {
      // The form was submitted, but not via the primary button, which
      // indicates that it will probably be rebuilt.
      return;
    }

    self::doExecuteLibrarySubmitHandlers($form, $form_state);
  }

  /**
   * Calls the #charts_library_settings_element_submit callbacks recursively.
   *
   * @param array &$element
   *   The current element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function doExecuteLibrarySubmitHandlers(array &$element, FormStateInterface $form_state) {
    // Recurse through all children.
    foreach (Element::children($element) as $key) {
      if (!empty($element[$key])) {
        static::executeLibraryElementSubmitHandlers($element[$key], $form_state);
      }
    }

    // If there are callbacks on this level, run them.
    if (!empty($element['#charts_library_settings_element_submit'])) {
      foreach ($element['#charts_library_settings_element_submit'] as $callback) {
        call_user_func_array($callback, [&$element, &$form_state]);
      }
    }
  }

  /**
   * Adds the library element options form.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The element.
   */
  private static function addLibraryElementOptions(array $element, FormStateInterface $form_state, array &$complete_form = []) {
    $used_in = $element['#used_in'] ?: '';
    if ($used_in === 'config_form') {
      return $element;
    }

    // @todo get the configuration from the config form.
    $plugin_configuration = $element['#value']['library_config'] ?? [];
    /** @var \Drupal\charts\ChartManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.charts');
    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
    $plugin = $plugin_manager->createInstance($element['#value']['library'], $plugin_configuration);
    $element['library_type_options'] = [
      '#type' => 'fieldset',
      '#title' => new TranslatableMarkup('Library and chart type-specific options'),
      '#description' => new TranslatableMarkup('These options are specific to the @library library in combination with the @type chart type.', [
        '@library' => $plugin->getPluginDefinition()['name'],
        '@type' => $element['type']['#options'][$element['#value']['type']],
      ]),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#weight' => 5,
      '#chart_type' => $element['#value']['type'],
    ];
    $type_element = &$element['library_type_options'];
    $type_options = $element['#value']['library_type_options'] ?? [];
    $plugin->addBaseSettingsElementOptions($type_element, $type_options, $form_state, $complete_form);

    return $element;
  }

  /**
   * Process form.
   *
   * @param array $element
   *   The current element.
   * @param array $options
   *   Options.
   *
   * @return array
   *   The element.
   */
  private static function processBasicForm(array $element, array $options) {
    $element_name = $element['#name'];

    $element['display']['stacking'] = [
      '#title' => new TranslatableMarkup('Enable stacking'),
      '#type' => 'checkbox',
      '#description' => new TranslatableMarkup('Enable stacking for this chart. Will stack based on the selected label field.'),
      '#default_value' => !empty($options['display']['stacking']),
    ];

    $element['yaxis']['inherit'] = [
      '#title' => new TranslatableMarkup('Add a secondary y-axis'),
      '#type' => 'checkbox',
      '#default_value' => $options['yaxis']['inherit'] ?? FALSE,
      '#description' => new TranslatableMarkup('Only one additional (secondary) y-axis can be created.'),
    ];

    $element['yaxis']['secondary'] = [
      '#title' => new TranslatableMarkup('Secondary vertical axis'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#attributes' => ['class' => ['chart-yaxis']],
      '#states' => [
        'visible' => [
          ':input[name="' . $element_name . '[yaxis][inherit]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['yaxis']['secondary']['title'] = [
      '#title' => new TranslatableMarkup('Custom title'),
      '#type' => 'textfield',
      '#default_value' => $options['yaxis']['secondary']['title'] ?? '',
    ];

    $element['yaxis']['secondary']['min_max_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => new TranslatableMarkup('Value range'),
    ];
    $element['yaxis']['secondary']['min'] = [
      '#type' => 'number',
      '#title' => new TranslatableMarkup('Value range minimum'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('Minimum'),
      ],
      '#max' => 999999999,
      '#size' => 12,
      '#suffix' => ' ',
      '#default_value' => $options['yaxis']['secondary']['min'] ?? '',
    ];
    $element['yaxis']['secondary']['max'] = [
      '#type' => 'number',
      '#title' => new TranslatableMarkup('Value range maximum'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('Maximum'),
      ],
      '#max' => 999999999,
      '#size' => 12,
      '#default_value' => $options['yaxis']['secondary']['max'] ?? '',
    ];

    $element['yaxis']['secondary']['prefix'] = [
      '#title' => new TranslatableMarkup('Value prefix'),
      '#type' => 'textfield',
      '#size' => 12,
      '#default_value' => $options['yaxis']['secondary']['prefix'] ?? '',
    ];

    $element['yaxis']['secondary']['suffix'] = [
      '#title' => new TranslatableMarkup('Value suffix'),
      '#type' => 'textfield',
      '#size' => 12,
      '#default_value' => $options['yaxis']['secondary']['suffix'] ?? '',
    ];

    $element['yaxis']['secondary']['decimal_count'] = [
      '#title' => new TranslatableMarkup('Decimal count'),
      '#type' => 'number',
      '#attributes' => [
        'placeholder' => new TranslatableMarkup('auto'),
      ],
      '#max' => 20,
      '#min' => 0,
      '#size' => 5,
      '#description' => new TranslatableMarkup('Enforce a certain number of decimal-place digits in displayed values.'),
      '#default_value' => $options['yaxis']['secondary']['decimal_count'] ?? '',
    ];

    $element['yaxis']['secondary']['labels_rotation'] = [
      '#title' => new TranslatableMarkup('Labels rotation'),
      '#type' => 'select',
      '#options' => [
        0 => new TranslatableMarkup('0°'),
        15 => new TranslatableMarkup('15°'),
        30 => new TranslatableMarkup('30°'),
        45 => new TranslatableMarkup('45°'),
        60 => new TranslatableMarkup('60°'),
        90 => new TranslatableMarkup('90°'),
      ],
      // This is only shown on inverted charts.
      '#attributes' => ['class' => ['axis-inverted-show']],
      '#default_value' => $options['yaxis']['secondary']['labels_rotation'] ?? '',
    ];

    return $element;
  }

  /**
   * Process view form.
   *
   * @param array $element
   *   The current element.
   * @param array $options
   *   The options.
   * @param array $complete_form
   *   The complete form where the element is attached to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   */
  private static function processViewForm(array $element, array $options, array &$complete_form, FormStateInterface $form_state) {
    if (!is_array($element['#field_options'])) {
      throw new \InvalidArgumentException('The chart_base_settings element need valid field options when used as view form.');
    }

    $element['display']['#weight'] = 2;
    $element['xaxis']['#weight'] = 2;
    $element['yaxis']['#weight'] = 2;

    $element_name = $element['#name'];
    $field_options = $element['#field_options'];
    $first_field = $field_options ? key($field_options) : '';

    $element['fields'] = [
      '#title' => new TranslatableMarkup('Charts fields'),
      '#type' => 'fieldset',
      '#weight' => 1,
    ];

    // Add a views-specific chart option to allow advanced rendering.
    // $element['fields']['allow_advanced_rendering'] = [
    // '#type' => 'checkbox',
    // '#title' => new TranslatableMarkup('Allow advanced rendering'),
    // '#description' => new TranslatableMarkup('Allow views field rewriting.
    // etc. for label and data fields. This can break charts if you rewrite
    // the field to a value the charting library cannot handle
    // - e.g. passing a string value into a numeric data column.'),
    // '#default_value' => isset($options['fields'].
    // ['allow_advanced_rendering']) ? $options['fields']
    // ['allow_advanced_rendering'] : NULL,].
    $element['fields']['label'] = [
      '#type' => 'radios',
      '#title' => new TranslatableMarkup('Label field'),
      '#options' => $field_options + ['' => new TranslatableMarkup('No label field')],
      '#default_value' => $options['fields']['label'] ?? $first_field,
    ];

    // Enable stacking.
    $element['fields']['stacking'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Stacking'),
      '#description' => new TranslatableMarkup('Enable stacking for this chart. Will stack based on the selected label field.'),
      '#default_value' => !empty($options['fields']['stacking']) ? $options['fields']['stacking'] : FALSE,
    ];

    $element['fields']['data_providers'] = [
      '#type' => 'table',
      '#header' => [
        new TranslatableMarkup('Field Name'),
        new TranslatableMarkup('Provides Data'),
        new TranslatableMarkup('Color'),
        new TranslatableMarkup('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'view-chart-fields-data-providers-order-weight',
        ],
      ],
    ];
    // Make the weight list always reflect the current number of values.
    // Taken from WidgetBase::formMultipleElements().
    $max_weight = count($field_options);

    $field_options_count = 0;
    $default_colors = $options['display']['colors'] ?? [];
    foreach ($field_options as $field_name => $field_label) {
      $field_option_element = &$element['fields']['data_providers'][$field_name];
      $default_value = $options['fields']['data_providers'][$field_name] ?? [];
      $default_weight = $default_value['weight'] ?? $max_weight;
      $default_color = $default_value['color'] ?? '';
      if (!$default_color) {
        $default_color = $default_colors[$field_options_count] ?? '#000000';
      }

      $field_option_element['#attributes']['class'][] = 'draggable';
      // Field option label.
      $field_option_element['label'] = [
        '#markup' => new TranslatableMarkup('@label', [
          '@label' => $field_label,
        ]),
      ];

      $field_option_element['enabled'] = [
        '#type' => 'checkbox',
        '#title' => new TranslatableMarkup('Provides data'),
        '#title_display' => 'invisible',
        '#default_value' => !empty($default_value['enabled']),
        '#states' => [
          'disabled' => [
            ':input[name="' . $element_name . '[fields][label]"]' => ['value' => $field_name],
          ],
        ],
      ];

      $field_option_element['color'] = [
        '#type' => 'textfield',
        '#title' => new TranslatableMarkup('Color'),
        '#attributes' => [
          'TYPE' => 'color',
          'style' => 'min-width:50px;',
        ],
        '#title_display' => 'invisible',
        '#size' => 10,
        '#maxlength' => 7,
        '#default_value' => $default_color,
      ];

      $field_option_element['weight'] = [
        '#type' => 'weight',
        '#title' => new TranslatableMarkup('Weight'),
        '#title_display' => 'invisible',
        '#delta' => $max_weight,
        '#default_value' => $default_weight,
        '#attributes' => [
          'class' => ['view-chart-fields-data-providers-order-weight'],
        ],
      ];

      $field_option_element['#weight'] = $default_weight;
      $field_options_count++;
    }

    $element['fields']['entity_grouping'] = [
      '#title' => new TranslatableMarkup('Entity grouping settings'),
      '#type' => 'fieldset',
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#weight' => 2,
      '#description' => new TranslatableMarkup('When grouping by an entity reference field, you can set the colors by entities or by a <a href="@href">color field</a>  attached to the entity type bundle in question.', [
        '@href' => 'https://drupal.org/project/color_field',
      ]),
      '#description_display' => 'before',
    ];
    $module_handler = \Drupal::moduleHandler();
    if (empty($element['#view_charts_style_plugin'])) {
      return $element;
    }

    // Entity grouping settings.
    // Try to get it from $form_state.
    $style_options_values = $form_state->getValue(['style_options'], []);
    $grouping_field = $style_options_values['grouping'][0] ?? [];
    $grouping_field_name = $grouping_field['field'] ?? '';
    $grouping_field_element = $complete_form['options']['style_options']['grouping'][0]['field'] ?? [];
    $triggering_element = $form_state->getTriggeringElement();
    if (!$grouping_field_name && !$triggering_element && $grouping_field_element) {
      // Get the grouping field name from default value property.
      $grouping_field_name = $grouping_field_element['#default_value'] ?? '';
    }
    if (!$grouping_field_name) {
      return $element;
    }

    // Check which selection method the user want to go with.
    /** @var \Drupal\charts\Plugin\views\style\ChartsPluginStyleChart $style_plugin */
    $style_plugin = $element['#view_charts_style_plugin'];
    $view = $style_plugin->view;
    $selection_method_wrapper_id = $view->id() . '--' . $view->current_display . '--' . $style_plugin->getPluginId() . '--fields--entity-grouping--color-selection-method';
    $selected_method = $options['fields']['entity_grouping']['color_selection_method'] ?? 'by_entities_on_entity_reference';
    $element['fields']['entity_grouping']['color_selection_method'] = [
      '#type' => 'radios',
      '#title' => new TranslatableMarkup('Color selection method'),
      '#required' => TRUE,
      '#options' => [
        'by_entities_on_entity_reference' => new TranslatableMarkup('Set color by entities on entity reference'),
      ],
      '#default_value' => $selected_method,
      '#ajax' => [
        'wrapper' => $selection_method_wrapper_id,
        'callback' => [
          get_called_class(),
          'groupingChartSettingsSelectedMethodAjaxCallback',
        ],
      ],
      '#limit_validation_errors' => [],
    ];
    if ($module_handler->moduleExists('color_field')) {
      $element['fields']['entity_grouping']['color_selection_method']['#options']['by_field_on_referenced_entity'] = new TranslatableMarkup('Set color based on a color field on entity reference');
    }
    $element['fields']['entity_grouping']['selected_method'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $selection_method_wrapper_id . '">',
      '#suffix' => '</div>',
      'colors' => [
        // Empty placeholder.
        '#markup' => '',
      ],
      'color_field_name' => [
        // Empty placeholder.
        '#markup' => '',
      ],
    ];

    $fields = $style_plugin->displayHandler->getOption('fields');
    $grouping_field_info = $fields[$grouping_field_name];
    // Get the entity type id of the reference field.
    if (!($entity_type_id = static::getReferenceEntityTypeId($grouping_field_info, $selected_method)) || empty($grouping_field_info['field'])) {
      return $element;
    }

    $metadata = [
      'grouping_field_name' => $grouping_field_info['field'],
      'selected_method' => $selected_method,
      'entity_type_id' => $entity_type_id,
    ];
    $entity_type_manager = \Drupal::entityTypeManager();
    switch ($selected_method) {
      case 'by_entities_on_entity_reference':
        $metadata['colors'] = $options['fields']['entity_grouping']['selected_method']['colors'] ?? [];
        $element['fields']['entity_grouping']['selected_method']['colors'] = static::buildColorsSelectionSubFormByEntities($metadata, $entity_type_manager);
        break;

      case 'by_field_on_referenced_entity':
        $metadata['color_field_name'] = $options['fields']['entity_grouping']['selected_method']['color_field_name'] ?? '';
        $element['fields']['entity_grouping']['selected_method']['color_field_name'] = static::buildColorsSelectionSubFormByFieldOnReferencedEntity($metadata, $entity_type_manager);
        break;
    }

    return $element;
  }

  /**
   * Process config form.
   *
   * @param array $element
   *   The current element.
   * @param array $options
   *   Options.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   */
  private static function processConfigForm(array $element, array $options, FormStateInterface $form_state): array {
    $parents = $element['#parents'];
    $tab_group = implode('][', array_merge($parents, ['defaults']));
    $display_parents = array_merge($parents, ['display']);
    $element['defaults'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-' . implode('-', $display_parents),
    ];
    $element['display']['#type'] = 'details';
    $element['display']['#weight'] = 1;
    $element['display']['#group'] = $tab_group;

    $element['xaxis']['#type'] = 'details';
    $element['xaxis']['#weight'] = 2;
    $element['xaxis']['#group'] = $tab_group;

    $element['yaxis']['#type'] = 'details';
    $element['yaxis']['#weight'] = 3;
    $element['yaxis']['#group'] = $tab_group;

    $id_prefix = implode('-', $parents);
    $element_state = static::getElementState($parents, $form_state);
    $state_color_indexes_key = $id_prefix . '__default_display_color_indexes';
    $options_display_colors = $options['display']['colors'];
    if (!$element_state) {
      $element_state = $options;
      $options_display_colors_indexes = !empty($options_display_colors) ? array_keys($options_display_colors) : [];
      $element_state[$state_color_indexes_key] = $options_display_colors_indexes;
      static::setElementState($parents, $form_state, $element_state);
    }
    else {
      $options_display_colors_indexes = $element_state[$state_color_indexes_key];
    }

    $wrapper_id = $id_prefix . '--display-colors';
    $element['display']['colors'] = [
      '#type' => 'table',
      '#header' => [
        new TranslatableMarkup('Colors'),
        new TranslatableMarkup('Operations'),
      ],
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    // Using the default colors in the settings to populate the colors.
    foreach ($options_display_colors_indexes as $color_index => $position) {
      $element['display']['colors'][$color_index]['color'] = [
        '#type' => 'textfield',
        '#title' => new TranslatableMarkup('Color'),
        '#title_display' => 'invisible',
        '#attributes' => [
          'TYPE' => 'color',
          'style' => 'min-width:50px;',
        ],
        '#size' => 10,
        '#maxlength' => 7,
        '#theme_wrappers' => [],
      ];
      if ($position !== '_new') {
        $element['display']['colors'][$color_index]['color']['#default_value'] = $options_display_colors[$color_index];
      }
      else {
        // Generating a random color for the new default color.
        $element['display']['colors'][$color_index]['color']['#default_value'] = static::randomColor();
      }
      $element['display']['colors'][$color_index]['remove'] = [
        '#type' => 'submit',
        '#name' => 'remove_value_display_colors_color' . $color_index,
        '#value' => new TranslatableMarkup('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [
          [get_called_class(), 'removeDefaultDisplayColorItemSubmit'],
        ],
        '#color_index' => $color_index,
        '#ajax' => [
          'callback' => [get_called_class(), 'defaultDisplayColorItemsAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#operation' => 'remove',
        '#state_color_indexes_key' => $state_color_indexes_key,
      ];
    }

    $element['display']['colors']['_add_new'] = [
      '#tree' => FALSE,
    ];
    $element['display']['colors']['_add_new']['add_item'] = [
      '#type' => 'container',
      '#wrapper_attributes' => ['colspan' => 2],
      '#tree' => FALSE,
    ];
    $element['display']['colors']['_add_new']['add_item']['submit'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Add a new default color'),
      '#submit' => [[get_called_class(), 'addDefaultDisplayColorItemSubmit']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [get_called_class(), 'defaultDisplayColorItemsAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#operation' => 'add',
      '#state_color_indexes_key' => $state_color_indexes_key,
    ];

    $element['display']['color_changer'] = [
      '#title' => new TranslatableMarkup('Expose color changer'),
      '#type' => 'checkbox',
      '#description' => new TranslatableMarkup('Display a widget that enables users to switch the chart colors.'),
      '#default_value' => !empty($options['display']['color_changer']),
      '#weight' => 10,
    ];

    return $element;
  }

  /**
   * Process series form.
   *
   * @param array $element
   *   The current element.
   * @param array $options
   *   The options.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element.
   *
   * @throws \Exception
   */
  private static function processSeriesForm(array $element, array $options, FormStateInterface $form_state) {
    // Chart preview.
    $parents = $element['#parents'];
    $css_id_prefix = implode('-', $parents);
    $id_prefix = str_replace('-', '_', $css_id_prefix);
    $open_preview_elt_state_key = $id_prefix . '__open_preview';
    $element_state = ChartDataCollectorTable::getElementState($parents, $form_state);

    if (!$element_state) {
      $element_state = $options;
      // Closing preview here cause this is probably initial form load.
      $open_preview = FALSE;
      $element_state[$open_preview_elt_state_key] = $open_preview;
      ChartDataCollectorTable::setElementState($parents, $form_state, $element_state);
    }
    else {
      $open_preview = $element_state[$open_preview_elt_state_key];
    }

    $wrapper_id = $css_id_prefix . '--preview--ajax-wrapper';
    $element['preview'] = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Preview'),
      '#weight' => -99,
      '#open' => $open_preview,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $element['preview']['submit'] = [
      '#type' => 'submit',
      '#value' => new TranslatableMarkup('Update Preview'),
      '#name' => $id_prefix . '__preview_submit',
      '#attributes' => [
        'class' => [$css_id_prefix . '--preview-submit'],
      ],
      '#submit' => [[get_called_class(), 'chartPreviewSubmit']],
      '#limit_validation_errors' => [$parents],
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefreshPreview'],
        'progress' => ['type' => 'throbber'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
      '#operation' => 'preview',
      '#open_preview_elt_state_key' => $open_preview_elt_state_key,
    ];

    if (!empty($element_state['library']) && !empty($element_state['series'])) {
      $chart_id = $id_prefix . '__preview_chart';
      $chart_build = Chart::buildElement($options, $chart_id);
      $chart_build['#id'] = $id_prefix . '--preview-chart';
      // @todo check if this would work with various hooks.
      $chart_build['#chart_id'] = $chart_id;
      $chart_build['#in_preview_mode'] = TRUE;
      $element['preview']['content'] = $chart_build;
    }
    else {
      $element['preview']['content'] = [
        '#markup' => new TranslatableMarkup('<p>Please fill up the required value below then update the preview.</p>'),
      ];
    }

    if (!empty($element['#default_value'])) {
      $options = NestedArray::mergeDeep($options, $element['#default_value']);
    }
    $element['series'] = [
      '#type' => 'chart_data_collector_table',
      '#initial_rows' => $element['#table_initial_rows'] ?? 5,
      '#initial_columns' => $element['#table_initial_columns'] ?? 2,
      '#table_drag' => FALSE,
      '#default_value' => $options['series'] ?? [],
      '#default_colors' => $options['display']['colors'] ?? [],
    ];

    return $element;
  }

  /**
   * Preview refresh Ajax callback.
   */
  public static function ajaxRefreshPreview(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    return $element['preview'];
  }

  /**
   * Submit callback for the preview button.
   */
  public static function chartPreviewSubmit(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#parents'], 0, -2);

    // Getting the current element state.
    $element_state = ChartDataCollectorTable::getElementState($element_parents, $form_state);
    $element_state[$triggering_element['#open_preview_elt_state_key']] = TRUE;
    // Updating form state storage.
    ChartDataCollectorTable::setElementState($element_parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Grouping chart settings ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array of the chart settings.
   */
  public static function groupingChartSettingsSelectedMethodAjaxCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $entity_grouping_element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    return $entity_grouping_element['selected_method'];
  }

  /**
   * Submit callback for adding a new default display color item value.
   */
  public static function addDefaultDisplayColorItemSubmit(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#array_parents'], 0, -5);
    $element_state = static::getElementState($element_parents, $form_state);

    // Adding a new default color color item index.
    $element_state[$triggering_element['#state_color_indexes_key']][] = '_new';
    // Updating form state storage.
    static::setElementState($element_parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for removing a value.
   */
  public static function removeDefaultDisplayColorItemSubmit(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#array_parents'], 0, -4);
    $element_state = static::getElementState($element_parents, $form_state);
    $color_index = $triggering_element['#color_index'];

    // Removing the color item.
    unset($element_state[$triggering_element['#state_color_indexes_key']][$color_index]);
    // Updating form state storage.
    static::setElementState($element_parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for then default display colors operations.
   */
  public static function defaultDisplayColorItemsAjax(array $form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $slice_length = $triggering_element['#operation'] === 'add' ? -4 : -3;
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, $slice_length));
    return $element['colors'];
  }

  /**
   * Builds the chart library configuration form into the settings.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $library
   *   The chart library.
   *
   * @return array
   *   The configuration subform.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private static function buildLibraryConfigurationForm(array $element, FormStateInterface $form_state, string $library) {
    $plugin_configuration = $element['#value']['library_config'] ?? [];
    // Using plugins to get the available installed libraries.
    /** @var \Drupal\charts\ChartManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.charts');
    /** @var \Drupal\charts\Plugin\chart\Library\ChartInterface $plugin */
    $plugin = $plugin_manager->createInstance($library, $plugin_configuration);
    $element['library_config'] = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('@library settings', [
        '@library' => $plugin->getPluginDefinition()['name'],
      ]),
      '#group' => $element['display']['#group'],
      '#weight' => 4,
    ];
    $element['library_config'] = $plugin->buildConfigurationForm($element['library_config'], $form_state);
    return $element;
  }

  /**
   * Helper method to retrieve the referenced entity type id.
   *
   * @param array $field_info
   *   The field info.
   * @param string $selection_method
   *   The selection method.
   *
   * @return string
   *   The entity type id.
   */
  private static function getReferenceEntityTypeId(array $field_info, string $selection_method): string {
    if (!$selection_method || empty($field_info['type']) || $field_info['type'] !== 'entity_reference_label' || empty($field_info['field'])) {
      return '';
    }
    $table = Views::viewsData()->get($field_info['table']);
    $field_machine_name = $field_info['field'];
    return $table[$field_machine_name]['relationship']['entity type'] ?? '';
  }

  /**
   * Helper method to build a color selection element by entities.
   *
   * @param array $metadata
   *   The metadata information to use to retrieve the color options.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return array
   *   The color selection table or a markup message when the provided metadata
   *   are incomplete.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private static function buildColorsSelectionSubFormByEntities(array $metadata, EntityTypeManagerInterface $entity_type_manager): array {
    $empty_entity_colors = new TranslatableMarkup("No grouping by an entity reference field was detected or the selected field did not have any entity or color field attached.");
    $colors = [
      '#markup' => '<p>' . $empty_entity_colors . '</p>',
    ];
    if (empty($metadata['grouping_field_name']) || empty($metadata['entity_type_id'])) {
      return $colors;
    }

    // Identifying the vocabulary this field could belong to.
    $field_config_storage = $entity_type_manager->getStorage('field_config');
    /** @var \Drupal\field\FieldConfigInterface[] $grouping_field_configs */
    $grouping_field_configs = $field_config_storage->loadByProperties(['field_name' => $metadata['grouping_field_name']]);
    $entity_type_id = $metadata['entity_type_id'];
    $bundle_ids = [];
    foreach ($grouping_field_configs as $grouping_field_config) {
      $field_settings = $grouping_field_config->getSettings();
      if (empty($field_settings['target_type']) || $field_settings['target_type'] !== $entity_type_id) {
        continue;
      }
      $target_bundles = $field_settings['handler_settings']['target_bundles'] ?? [];
      foreach ($target_bundles as $bundle_id) {
        $bundle_ids[$bundle_id] = $bundle_id;
      }
    }

    // Load the entities by bundle.
    $entity_storage = $entity_type_manager->getStorage($entity_type_id);
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);

    $colors = [
      '#type' => 'table',
      '#empty'  => $empty_entity_colors,
      '#header' => [
        new TranslatableMarkup('Entity label'),
        new TranslatableMarkup('Bundle'),
        new TranslatableMarkup('Color'),
      ],
    ];
    $bundle_key = $entity_type->getKey('bundle');
    $query = $entity_storage->getQuery()->accessCheck(FALSE)
      ->condition($bundle_key, $bundle_ids, 'IN');

    if ($entity_type instanceof EntityTypeInterface) {
      $published_key = $entity_type->getKey('published');
      $query->condition($published_key, TRUE);
    }

    // For now limiting to 150 entities.
    $entity_ids = $query->range(0, 150)
      ->execute();
    $has_uuid_key = $entity_type->hasKey('uuid');
    foreach ($entity_ids as $id) {
      $entity = $entity_storage->load($id);
      $color_id_key = $has_uuid_key ? $entity->get('uuid')->value : $entity->id();
      $field_option_element = &$colors[$color_id_key];
      $default_value = $metadata['colors'][$color_id_key]['color'] ?? '#000000';

      $label = $entity->label();
      $field_option_element['label'] = [
        '#markup' => new TranslatableMarkup('@label', [
          '@label' => $label,
        ]),
      ];
      $field_option_element['bundle'] = [
        '#markup' => new TranslatableMarkup('@bundle', [
          '@bundle' => $entity->bundle(),
        ]),
      ];
      $field_option_element['color'] = [
        '#type' => 'textfield',
        '#title' => new TranslatableMarkup('@label', ['@label' => $label]),
        '#title_display' => 'invisible',
        '#attributes' => [
          'TYPE' => 'color',
          'style' => 'min-width:50px;',
        ],
        '#size' => 10,
        '#maxlength' => 7,
        '#default_value' => $default_value,
      ];
    }

    return $colors;
  }

  /**
   * Method to build a color selection element by field on referenced entity.
   *
   * @param array $metadata
   *   The metadata information to use to retrieve the color options.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return array
   *   The select element or the status message when no color options was
   *   found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private static function buildColorsSelectionSubFormByFieldOnReferencedEntity(array $metadata, EntityTypeManagerInterface $entity_type_manager): array {
    // Identifying the vocabulary this field could belong to.
    $field_config_storage = $entity_type_manager->getStorage('field_config');
    /** @var \Drupal\field\FieldConfigInterface[] $grouping_field_configs */
    $grouping_field_configs = $field_config_storage->loadByProperties(['field_name' => $metadata['grouping_field_name']]);
    $entity_type_id = $metadata['entity_type_id'];
    $processed_bundle_ids = [];
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $color_field_options = [];
    foreach ($grouping_field_configs as $grouping_field_config) {
      $field_settings = $grouping_field_config->getSettings();
      if (empty($field_settings['target_type']) || $field_settings['target_type'] !== $entity_type_id) {
        continue;
      }

      $target_bundles = $field_settings['handler_settings']['target_bundles'] ?? [];
      foreach ($target_bundles as $bundle_id) {
        if (in_array($bundle_id, $processed_bundle_ids)) {
          continue;
        }

        // Extract fields from bundle fields.
        foreach ($entity_field_manager->getFieldDefinitions($entity_type_id, $bundle_id) as $field_name => $field_definition) {
          if ($field_definition->getType() !== 'color_field_type' || !empty($color_field_options[$field_name])) {
            continue;
          }
          $color_field_options[$field_name] = $field_definition->getLabel();
        }
        $processed_bundle_ids[] = $bundle_id;
      }
    }

    if (!$color_field_options) {
      $empty_entity_field_name = new TranslatableMarkup("You can't set the color using the selected method because the referenced entity doesn't have any color field type configured.");
      return [
        '#theme' => 'status_messages',
        '#message_list' => ['warning' => [$empty_entity_field_name]],
        '#status_headings' => [
          'warning' => new TranslatableMarkup('Warning message'),
        ],
      ];
    }

    return [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Color field'),
      '#description' => new TranslatableMarkup('The color field on which we should get the color data from.'),
      '#options' => $color_field_options,
      '#default_value' => $metadata['color_field_name'] ?? '',
      '#required' => TRUE,
    ];
  }

}
