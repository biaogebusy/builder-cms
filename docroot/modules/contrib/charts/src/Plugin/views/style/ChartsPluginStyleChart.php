<?php

namespace Drupal\charts\Plugin\views\style;

use Drupal\charts\ChartManager;
use Drupal\charts\ChartViewsFieldInterface;
use Drupal\charts\Element\BaseSettings;
use Drupal\charts\Plugin\chart\Library\ChartInterface;
use Drupal\charts\TypeManager;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render view as a chart.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "chart",
 *   title = @Translation("Chart"),
 *   help = @Translation("Render a chart of your data."),
 *   theme = "views_view_charts",
 *   display_types = { "normal" }
 * )
 */
class ChartsPluginStyleChart extends StylePluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The chart manager service.
   *
   * @var \Drupal\charts\ChartManager
   */
  protected ChartManager $chartManager;

  /**
   * The chart type manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected TypeManager $chartTypeManager;

  /**
   * The label field key.
   *
   * @var string
   */
  protected string $labelFieldKey;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a ChartsPluginStyleChart object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\charts\ChartManager $chart_manager
   *   The chart manager service.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ChartManager $chart_manager, TypeManager $chart_type_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->chartManager = $chart_manager;
    $this->chartTypeManager = $chart_type_manager;
    $this->routeMatch = $route_match;
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
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $charts_settings = $this->configFactory->get('charts.settings');
    $charts_default_settings = $charts_settings->get('charts_default_settings') ?? [];
    $options['chart_settings'] = [
      'default' => $charts_default_settings,
    ];
    $options['chart_settings']['fields']['allow_advanced_rendering'] = FALSE;

    $options['chart_settings']['library'] = '';

    // @todo ensure that chart extensions inherit defaults from parent
    // Remove the default setting for chart type so it can be inherited if this
    // is a chart extension type.
    $style_plugin = $this->view->style_plugin ?? NULL;
    $style_plugin_id = $style_plugin ? $style_plugin->getPluginId() : '';
    if ($style_plugin_id === 'chart_extension') {
      $options['chart_settings']['default']['type'] = NULL;
    }
    $options['path'] = ['default' => 'charts'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $handlers = $this->displayHandler->getHandlers('field');
    if (empty($handlers)) {
      $form['error_markup'] = ['#markup' => '<div class="error messages">' . $this->t('You need at least one field before you can configure your chart settings') . '</div>'];
      return;
    }

    $settings_wrapper = 'views-charts-plugin-style-chart-options-settings-wrapper';
    // Limit grouping options (we only support one grouping field).
    if (isset($form['grouping'][0])) {
      $form['grouping'][0]['field']['#title'] = $this->t('Grouping field');
      $form['grouping'][0]['field']['#description'] = $this->t('If grouping by a particular field, that field will be used to determine stacking of the chart. Generally this will be the same field as what you select for the "Label field" below. If you do not have more than one "Provides data" field below, there will be nothing to stack. If you want to have another series displayed, use a "Chart attachment" display, and set it to attach to this display.');
      $form['grouping'][0]['field']['#attributes']['class'][] = 'charts-grouping-field';
      // Grouping by rendered version has no effect in charts. Hide the options.
      $form['grouping'][0]['rendered']['#access'] = FALSE;
      $form['grouping'][0]['rendered_strip']['#access'] = FALSE;

      // Add ajax related to grouping to allow taxonomy colors selection when
      // the field is an entity reference.
      $form['grouping'][0]['field']['#ajax'] = [
        'wrapper' => $settings_wrapper,
        'callback' => [static::class, 'groupingChartSettingsAjaxCallback'],
      ];
    }
    if (isset($form['grouping'][1])) {
      $form['grouping'][1]['#access'] = FALSE;
    }

    // Merge in the global chart settings form.
    $field_options = $this->displayHandler->getFieldLabels();
    $form_state->set('default_options', $this->options);
    $form['chart_settings'] = [
      '#prefix' => '<div id="' . $settings_wrapper . '">',
      '#type' => 'charts_settings',
      '#used_in' => 'view_form',
      '#required' => TRUE,
      '#field_options' => $field_options,
      '#default_value' => $this->options['chart_settings'],
      '#suffix' => '</div>',
      '#view_charts_style_plugin' => $this,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    if (!$form_state->hasValue(['style_options', 'chart_settings'])) {
      return;
    }

    $chart_settings = $form_state->getValue(['style_options', 'chart_settings']);
    $selected_library_id = $chart_settings['library'] ?? '';
    if (empty($chart_settings['library'])) {
      $form_state->setError($form['chart_settings'], $this->t('Please select a valid charting library or <a href="/admin/modules">install</a> at least one module that implements a chart library plugin.'));
      return;
    }
    if (($selected_library_id === 'site_default' && !BaseSettings::getConfiguredSiteDefaultLibraryId()) || empty($chart_settings['type'])) {
      $destination = '/admin/structure/views/view/' . $this->view->storage->id();
      if ($this->view->current_display) {
        $destination .= '/' . $this->view->current_display;
      }
      $form_state->setError($form['chart_settings'], $this->t('The site default charting library has not been set yet, or it does not support chart type options. Please ensure that you have correctly <a href="@url">configured the chart module</a>.', [
        '@url' => '/admin/config/content/charts?destination=' . $destination,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    $chart_settings = $this->options['chart_settings'];
    $selected_library_id = $chart_settings['library'] ?? '';
    if (!$selected_library_id) {
      $errors[] = $this->t('Please select a valid charting library.');
      return $errors;
    }

    if ($selected_library_id === 'site_default' && !BaseSettings::getConfiguredSiteDefaultLibraryId()) {
      $destination = '/admin/structure/views/view/' . $this->view->storage->id();
      if ($this->view->current_display) {
        $destination .= '/' . $this->view->current_display;
      }
      $errors[] = $this->t('The site default charting library has not been set yet, or it does not support chart type options. Please ensure that you have correctly <a href="@url">configured the chart module</a>.', [
        '@url' => '/admin/config/content/charts?destination=' . $destination,
      ]);
      return $errors;
    }

    $selected_data_fields = !empty($chart_settings['fields']['data_providers']) && is_array($chart_settings['fields']['data_providers']) ?
      $this->getSelectedDataFields($chart_settings['fields']['data_providers']) : NULL;

    // Avoid calling validation before arriving at the view edit page.
    if ($this->routeMatch->getRouteName() != 'views_ui.add' && empty($selected_data_fields)) {
      $errors[] = $this->t('At least one data field must be selected in the chart configuration before this chart may be shown');
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $field_handlers = $this->view->getHandlers('field');
    $chart_settings = $this->options['chart_settings'];
    $chart_fields = $chart_settings['fields'];
    $is_grouped = isset($this->options['grouping'][0]['field']);

    // Calculate the labels field alias.
    $label_field_key = $this->getLabelFieldKey();

    // Assemble the fields to be used to provide data access.
    $field_keys = array_keys($this->getSelectedDataFields($chart_fields['data_providers']));
    $data_fields = array_filter($field_handlers, function ($field_handler) use ($field_keys, $label_field_key) {
      if (isset($field_handler['exclude']) && ($field_handler['exclude'] == FALSE || $field_handler['exclude'] == 0)) {
        $field_id = $field_handler['id'];
      }
      else {
        $field_id = '';
      }
      // Do not allow the label field to be used as a data field.
      return $field_id !== $label_field_key && in_array($field_id, $field_keys);
    });

    $title = !empty($chart_settings['display']['title']) ? $chart_settings['display']['title'] : '';
    $subtitle = !empty($chart_settings['display']['subtitle']) ? $chart_settings['display']['subtitle'] : '';
    if (!empty($title) || !empty($subtitle)) {
      $tokens = [];
      $global_tokens = [];
      foreach ($field_handlers as $field_id => $field_handler) {
        // This needs to run or else the values are empty.
        $this->getField(0, $field_id);
        // If the row index is not set, set it to 0.
        if (!isset($this->view->row_index)) {
          $this->view->row_index = 0;
        }
        $render_tokens = $this->view->field[$field_id]->getRenderTokens([]) ?? [];
        $global_tokens = array_merge($render_tokens, $global_tokens);
      }
      foreach ($global_tokens as $key => $value) {
        $tokens[$key] = Xss::filterAdmin($this->tokenizeValue($key, 0));
      }
      if (!empty($tokens)) {
        // Allow argument tokens in the title.
        $title = $this->viewsTokenReplace($title, $tokens);
        // Allow argument tokens in the subtitle.
        $subtitle = $this->viewsTokenReplace($subtitle, $tokens);
      }
    }

    // To be used with the exposed chart type field.
    if ($this->view->storage->get('exposed_chart_type')) {
      $chart_settings['type'] = $this->view->storage->get('exposed_chart_type');
    }

    $chart_id = $this->view->id() . '_' . $this->view->current_display;
    $chart = [
      '#type' => 'chart',
      '#chart_type' => $chart_settings['type'],
      '#chart_library' => $chart_settings['library'],
      '#chart_id' => $chart_id,
      '#id' => Html::getUniqueId('chart_' . $chart_id),
      '#stacking' => $chart_settings['fields']['stacking'] ?? '0',
      '#polar' => $chart_settings['display']['polar'],
      '#three_dimensional' => $chart_settings['display']['three_dimensional'],
      '#gauge' => $chart_settings['display']['gauge'],
      '#title' => $title,
      '#title_position' => $chart_settings['display']['title_position'],
      '#subtitle' => $subtitle,
      '#tooltips' => $chart_settings['display']['tooltips'],
      '#data_labels' => $chart_settings['display']['data_labels'],
      '#data_markers' => $chart_settings['display']['data_markers'],
      // Colors only used if a grouped view or using a type such as a pie chart.
      '#colors' => $chart_settings['display']['colors'] ?? [],
      '#background' => $chart_settings['display']['background'] ?? 'transparent',
      '#legend' => !empty($chart_settings['display']['legend_position']),
      '#legend_position' => $chart_settings['display']['legend_position'] ?? '',
      '#width' => $chart_settings['display']['dimensions']['width'],
      '#height' => $chart_settings['display']['dimensions']['height'],
      '#width_units' => $chart_settings['display']['dimensions']['width_units'],
      '#height_units' => $chart_settings['display']['dimensions']['height_units'],
      '#color_changer' => $chart_settings['display']['color_changer'] ?? FALSE,
      '#attributes' => ['data-drupal-selector-chart' => Html::getId($chart_id)],
      // Pass info about the actual view results to allow further processing.
      '#view' => $this->view,
    ];

    $chart_type = $this->chartTypeManager->getDefinition($chart_settings['type']);
    if ($chart_type['axis'] === ChartInterface::SINGLE_AXIS) {
      $data_field_key = key($data_fields);
      $data_field = $data_fields[$data_field_key];
      $data = [];
      $this->renderFields($this->view->result);
      $renders = $this->rendered_fields;
      if (!$label_field_key && count($data_fields) > 1) {
        foreach ($data_fields as $field_id => $row) {
          $data_row = [];
          if (!empty($row['label'])) {
            $data_row['name'] = strip_tags($row['label'], ENT_QUOTES);
          }
          else {
            $data_row['name'] = strip_tags($field_id, ENT_QUOTES);
          }
          if (!empty($chart_fields['data_providers'][$field_id]['color'])) {
            $data_row['color'] = $chart_fields['data_providers'][$field_id]['color'];
          }
          $data_row['y'] = $this->processNumberValueFromField(0, $field_id);
          $data[] = $data_row;
        }
      }
      else {
        foreach ($renders as $row_number => $row) {
          $data_row = [];
          if ($label_field_key) {
            // Labels need to be decoded; the charting library will re-encode.
            $data_row[] = strip_tags($this->getField($row_number, $label_field_key), ENT_QUOTES);
          }
          $data_row[] = $this->processNumberValueFromField($row_number, $data_field_key);
          $data[] = $data_row;
        }
      }

      // @todo create a textfield for chart legend title.
      // if ($chart_fields['label']) {
      // $chart['#legend_title'] = $chart_fields['label'];
      // }
      $chart[$this->view->current_display . '_series'] = [
        '#type' => 'chart_data',
        '#data' => $data,
        '#title' => $data_field['label'],
        '#color' => isset($chart_fields['data_providers'][$data_field_key]) ? $chart_fields['data_providers'][$data_field_key]['color'] : '',
        '#grouping_colors' => $this->extractGroupingColorsForSingleAxisChartType($data),
      ];
    }
    else {
      $chart['xaxis'] = [
        '#type' => 'chart_xaxis',
        '#title' => $chart_settings['xaxis']['title'] ?? '',
        '#labels_rotation' => $chart_settings['xaxis']['labels_rotation'],
      ];
      $chart['yaxis'] = [
        '#type' => 'chart_yaxis',
        '#title' => $chart_settings['yaxis']['title'] ?? '',
        '#labels_rotation' => $chart_settings['yaxis']['labels_rotation'],
        '#max' => $chart_settings['yaxis']['max'],
        '#min' => $chart_settings['yaxis']['min'],
      ];

      $view_records = $this->view->result;
      $sets = $this->renderGrouping($view_records, $this->options['grouping'], TRUE);
      if ($is_grouped) {
        $this->groupedChartElementBuild($chart, $sets, $data_fields);
      }
      else {
        $series_index = 0;
        foreach ($sets as $series_label => $data_set) {
          foreach ($data_fields as $field_key => $field_handler) {
            $element_key = $this->view->current_display . '__' . $field_key . '_' . $series_index;
            $chart[$element_key] = [
              '#type' => 'chart_data',
              '#data' => [],
              // If using a grouping field, inherit from the chart level colors.
              '#color' => ($series_label === '' && isset($chart_fields['data_providers'][$field_key])) ? $chart_fields['data_providers'][$field_key]['color'] : '',
              '#title' => $series_label ? strip_tags($series_label) : $field_handler['label'],
              '#prefix' => $chart_settings['yaxis']['prefix'] ?? NULL,
              '#suffix' => $chart_settings['yaxis']['suffix'] ?? NULL,
              '#decimal_count' => $chart_settings['yaxis']['decimal_count'] ?? '',
            ];
          }

          // Grouped results come back indexed by their original result number
          // from before the grouping, so we need to keep our own row number
          // when looping through the rows.
          foreach ($data_set['rows'] as $result_number => $row) {
            $xaxis_label = trim(strip_tags((string) $this->getField($result_number, $label_field_key)));
            if ($label_field_key) {
              $xaxis_labels = $chart['xaxis']['#labels'] ?? [];
              if (!in_array($xaxis_label, $xaxis_labels)) {
                $chart['xaxis']['#labels'][] = $xaxis_label;
              }
            }
            foreach ($data_fields as $field_key => $field_handler) {
              $element_key = $this->view->current_display . '__' . $field_key . '_' . $series_index;
              $value = $this->processNumberValueFromField($result_number, $field_key);
              $chart[$element_key]['#data'][] = $value;
              $chart[$element_key]['#mapped_data'][$xaxis_label] = $value;
              if (strpos($field_handler['id'], 'field_charts_fields_scatter') === 0 || strpos($field_handler['id'], 'field_charts_fields_bubble') === 0) {
                $chart['xaxis']['#labels'] = [];
              }
            }
          }

          // Incrementing series index.
          $series_index++;
        }
      }
    }

    // Check if this display has any children charts that should be applied
    // on top of it.
    $children_displays = $this->getChildrenChartDisplays();
    // Contains the different subviews of the attachments.
    $attachments = [];

    foreach ($children_displays as $child_display) {
      // If the user doesn't have access to the child display, skip.
      if (!$this->view->access($child_display)) {
        continue;
      }

      // Generate the subchart by executing the child display. We load a fresh
      // view here to avoid collisions in shifting the current display while in
      // a display.
      $subview = $this->view->createDuplicate();
      $subview->setDisplay($child_display);
      $child_display_handler = $this->view->displayHandlers->get($child_display);
      $child_display_settings = $subview->display_handler->options['style']['options']['chart_settings'];

      // Copy the settings for our axes over to the child view.
      foreach ($chart_settings as $option_name => $option_value) {
        if ($child_display_handler->options['inherit_yaxis'] === '1') {
          $child_display_settings[$option_name] = $option_value;
        }
      }

      // Set the arguments on the subview if it is configured to inherit;
      // arguments.
      if (!empty($child_display_handler->display['display_options']['inherit_arguments']) && $child_display_handler->display['display_options']['inherit_arguments'] == '1') {
        $subview->setArguments($this->view->args);
      }

      // Execute the subview and get the result.
      $subview->preExecute();
      $subview->execute();

      // If there's no results, don't attach the subview.
      if (empty($subview->result)) {
        continue;
      }

      $subchart = $subview->style_plugin->render();
      // Add attachment views to attachments array.
      array_push($attachments, $subview);

      // Create a secondary axis if needed.
      if ($child_display_handler->options['inherit_yaxis'] !== '1' && isset($subchart['yaxis'])) {
        $chart['secondary_yaxis'] = $subchart['yaxis'];
        $chart['secondary_yaxis']['#opposite'] = TRUE;
      }

      // Merge in the child chart data.
      foreach (Element::children($subchart) as $key) {
        if ($subchart[$key]['#type'] === 'chart_data') {
          // This ensures that chart attachment data are placed correctly,
          // but it doesn't allow for chart attachment data to have x-axis
          // labels not already present in the parent chart.
          if (!empty($chart['xaxis']['#labels'])) {
            $processed_data = $this->alignSubchartData($chart['xaxis']['#labels'], $subchart[$key]['#mapped_data'], $subchart[$key]['#data']);
            $subchart[$key]['#data'] = $processed_data;
          }
          $chart[$key] = $subchart[$key];
          // If the subchart is a different type than the parent chart, set
          // the #chart_type property on the individual chart data elements.
          if ($subchart['#chart_type'] !== $chart['#chart_type']) {
            $chart[$key]['#chart_type'] = $subchart['#chart_type'];
          }
          if ($child_display_handler->options['inherit_yaxis'] !== '1') {
            $chart[$key]['#target_axis'] = 'secondary_yaxis';
          }
        }
      }
    }

    // Print the chart.
    return $chart;
  }

  /**
   * {@inheritdoc}
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {
    if (empty($this->options['grouping'])) {
      return parent::renderGrouping($records, $groupings, $group_rendered);
    }

    $xaxis_labels = [];
    // Get the entire sets with grouping.
    $sets = [];
    // For the chart plugin the grouping level is always going to be at index
    // 0 since only one grouping is allowed.
    $grouping_level = 0;
    $grouping_field_info = $groupings[$grouping_level];
    $grouping_field = $grouping_field_info['field'];
    $xaxis_label_field_key = $this->getLabelFieldKey();
    $chart_settings = $this->options['chart_settings'];
    $color_selection_method = $chart_settings['fields']['entity_grouping']['color_selection_method'] ?? '';

    foreach ($records as $index => $row) {
      $set = &$sets;

      // Extract xaxis labels.
      if (isset($this->view->field[$xaxis_label_field_key])) {
        $xaxis_label = $this->getField($index, $xaxis_label_field_key);
        $xaxis_label = trim(strip_tags(htmlspecialchars_decode($xaxis_label)));
        if (!in_array($xaxis_label, $xaxis_labels, TRUE)) {
          $xaxis_labels[] = $xaxis_label;
        }
        $row->xaxis_label_index = array_flip($xaxis_labels)[$xaxis_label];
      }

      $grouping = '';
      $group_content = '';
      // Extract grouping content/label.
      if (isset($this->view->field[$grouping_field])) {
        $group_content = $this->getField($index, $grouping_field);
        $group_content = $grouping = trim(strip_tags(htmlspecialchars_decode($group_content)));
      }

      // Create the group if it does not exist yet.
      if (empty($set[$grouping])) {
        $grouping_entity_field = $this->view->field[$grouping_field];
        $group_field_name = $grouping_entity_field ? ($grouping_entity_field->definition['field_name'] ?? '') : '';
        if ($color_selection_method && $group_field_name && $grouping_entity_field instanceof EntityField && $row instanceof ResultRow) {
          switch ($color_selection_method) {
            case 'by_entities_on_entity_reference':
              $set[$grouping]['color'] = $this->extractGroupedSelectedColorByEntity($grouping_entity_field, $row, $group_field_name);
              break;

            case 'by_field_on_referenced_entity':
              $set[$grouping]['color'] = $this->extractGroupedSelectedColorOnReferencedEntityField($grouping_entity_field, $row, $group_field_name);
              break;
          }
        }

        $set[$grouping]['group'] = $group_content;
        $set[$grouping]['level'] = $grouping_level;
        $set[$grouping]['rows'] = [];
      }

      // Move the set reference into the set of the group we just determined.
      $set = &$set[$grouping]['rows'];
      // Add the row to the hierarchically positioned set we just determined.
      $set[$index] = $row;
    }

    // Adding a workaround to pass the xaxis labels of grouping to the calling
    // function.
    $sets['_charts_xaxis_labels'] = $xaxis_labels;

    return $sets;
  }

  /**
   * Utility function to check if this chart has children displays.
   *
   * @return array
   *   Children Chart Display.
   */
  public function getChildrenChartDisplays() {
    $children_displays = $this->displayHandler->getAttachedDisplays();
    foreach ($children_displays as $key => $child) {
      $display_handler = $this->view->displayHandlers->get($child);
      // Unset disabled & non chart attachments.
      if ((!$display_handler->isEnabled()) || (strstr($child, 'chart_extension') == !TRUE)) {
        unset($children_displays[$key]);
      }
    }

    return array_values($children_displays);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];

    if (!empty($this->options['chart_settings']['library'])) {
      $plugin_definition = $this->chartManager->getDefinition($this->options['chart_settings']['library']);
      $dependencies['module'] = [$plugin_definition['provider']];
    }

    return $dependencies;
  }

  /**
   * Processes number value based on field.
   *
   * @param int $number
   *   The number.
   * @param string $field
   *   The field.
   *
   * @return \Drupal\Component\Render\MarkupInterface|float|null
   *   The value.
   */
  public function processNumberValueFromField($number, $field) {
    if (is_array($this->getField($number, $field))) {
      $value = $this->getField($number, $field)->__toString();
    }
    else {
      $value = $this->getField($number, $field);
    }

    $value = trim(strip_tags($value));

    // Get the field plugin class to determine if a Charts-specific field
    // is being used.
    $field_plugin = $this->view->field[$field];
    if ($field_plugin instanceof ChartViewsFieldInterface && $field_plugin->getChartFieldDataType() === 'array') {
      return Json::decode($value);
    }

    // Convert empty strings to NULL.
    if ($value === '' || is_null($value)) {
      $value = NULL;
    }
    else {
      // Strip thousands placeholders if present, then cast to float.
      $value = (float) str_replace([',', ' '], '', $value);
    }

    return $value;
  }

  /**
   * Utility method to filter out unselected fields from data providers fields.
   *
   * @param array $data_providers
   *   The data providers.
   *
   * @return array
   *   The fields.
   */
  private function getSelectedDataFields(array $data_providers) {
    return array_filter($data_providers, function ($value) {

      return !empty($value['enabled']);
    });
  }

  /**
   * Returns the key of the Label Field.
   *
   * @return string
   *   The Label Field key.
   */
  private function getLabelFieldKey() {
    if (!isset($this->labelFieldKey)) {
      $field_handlers = $this->view->getHandlers('field');
      $chart_settings = $this->options['chart_settings'];
      $chart_fields = $chart_settings['fields'];
      $label_field = $field_handlers[$chart_fields['label']] ?? '';
      $this->labelFieldKey = $label_field ? $chart_fields['label'] : '';
    }

    return $this->labelFieldKey;
  }

  /**
   * Helper method to build the chart data elements of a grouped chart.
   *
   * @param array $chart
   *   The main chart element.
   * @param array $sets
   *   The grouped record set.
   * @param array $data_fields
   *   The selected data fields on the chart style options.
   */
  private function groupedChartElementBuild(array &$chart, array $sets, array $data_fields) {
    $original_xaxis = $chart['xaxis'];
    $xaxis_labels = [];
    $label_field_key = $this->getLabelFieldKey();
    if (!empty($sets['_charts_xaxis_labels'])) {
      $chart['xaxis']['#labels'] = $sets['_charts_xaxis_labels'];
      unset($sets['_charts_xaxis_labels']);
      // Flipping the labels here to get access to their index based on value.
      $xaxis_labels = array_flip($chart['xaxis']['#labels']);
    }

    $series_index = 0;
    $element_key_prefix = $this->view->current_display . '__' . $label_field_key;
    $chart_settings = $this->options['chart_settings'];
    foreach ($sets as $set_label => $data_set) {
      $name = strtolower(Html::cleanCssIdentifier($set_label));
      $element_key = $element_key_prefix . '__' . $name;
      $chart[$element_key] = [
        '#type' => 'chart_data',
        '#data' => $xaxis_labels ? array_fill(0, count($xaxis_labels), NULL) : [],
        // If using a grouping field, inherit from the chart level colors.
        '#title' => $set_label,
        '#prefix' => $chart_settings['yaxis']['prefix'] ?? NULL,
        '#suffix' => $chart_settings['yaxis']['suffix'] ?? NULL,
        '#decimal_count' => $chart_settings['yaxis']['decimal_count'] ?? '',
      ];
      if (!empty($data_set['color'])) {
        $chart[$element_key]['#color'] = $data_set['color'];
      }

      foreach ($data_set['rows'] as $result_number => $row) {
        $set_id = $row->xaxis_label_index ?? $series_index;
        foreach ($data_fields as $field_key => $field_handler) {
          // Don't allow the grouping field to provide data.
          if ($field_key === $this->options['grouping'][0]['field']) {
            continue;
          }
          $value = $this->processNumberValueFromField($result_number, $field_key);
          if (strpos($field_handler['id'], 'field_charts_fields_scatter') === 0 || strpos($field_handler['id'], 'field_charts_fields_bubble') === 0) {
            $chart[$element_key]['#data'] = [];
            $chart[$element_key]['#data'][] = $value;
            $chart['xaxis'] = $original_xaxis;
          }
          else {
            $chart[$element_key]['#data'][$set_id] = $value;
            $chart[$element_key]['#mapped_data'][$set_id] = $value;
          }
        }
      }

      // Incrementing series index.
      $series_index++;
    }
  }

  /**
   * Grouping chart settings ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\core\form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The render array of the chart settings.
   */
  public static function groupingChartSettingsAjaxCallback(array $form, FormStateInterface $form_state) {
    return $form['options']['style_options']['chart_settings'];
  }

  /**
   * Returns the selected color.
   *
   * @param \Drupal\views\Plugin\views\field\EntityField $view_entity_field
   *   The view entity field.
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   * @param string $group_field_name
   *   The grouping field name.
   *
   * @return string
   *   The color.
   */
  private function extractGroupedSelectedColorByEntity(EntityField $view_entity_field, ResultRow $row, string $group_field_name) {
    $chart_settings = $this->options['chart_settings'];
    $colors_settings = $chart_settings['fields']['entity_grouping']['selected_method']['colors'] ?? [];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $view_entity_field->getEntity($row);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
    $referenced_entity = $host_entity->get($group_field_name)->entity;
    if (!$referenced_entity || !$colors_settings) {
      return '';
    }

    $entity_type = $referenced_entity->getEntityType();
    $has_uuid_key = $entity_type->hasKey('uuid');
    $color_id_key = $has_uuid_key ? $referenced_entity->get('uuid')->value : $referenced_entity->id();
    return $colors_settings[$color_id_key]['color'] ?? '';
  }

  /**
   * Returns the color from the referenced entity field.
   *
   * @param \Drupal\views\Plugin\views\field\EntityField $view_entity_field
   *   The view entity field.
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   * @param string $group_field_name
   *   The grouping field name.
   *
   * @return string
   *   The color.
   */
  private function extractGroupedSelectedColorOnReferencedEntityField(EntityField $view_entity_field, ResultRow $row, string $group_field_name) {
    $chart_settings = $this->options['chart_settings'];
    $color_field_name = $chart_settings['fields']['entity_grouping']['selected_method']['color_field_name'] ?? '';
    if (!$color_field_name) {
      return '';
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $view_entity_field->getEntity($row);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
    $referenced_entity = $host_entity->get($group_field_name)->entity;
    $field_item_list = $referenced_entity ? $referenced_entity->get($color_field_name) : NULL;
    if (!$field_item_list || $field_item_list->isEmpty()) {
      return '';
    }

    /** @var \Drupal\color_field\Plugin\Field\FieldType\ColorFieldType $color_field */
    $color_field = $field_item_list->first();
    return $color_field->getFieldDefinition()->getType() === 'color_field_type' ? $color_field->color : '';
  }

  /**
   * Ensures chart attachments are placed correctly on chart.
   *
   * @param array $parent_labels
   *   The parent labels.
   * @param array $child_mapped_data
   *   The mapped data of the child display.
   * @param array $data
   *   The data.
   *
   * @return array
   *   $processed_data
   */
  private function alignSubchartData(array $parent_labels, array $child_mapped_data, array $data) {
    $child_labels = array_keys($child_mapped_data);
    if ($parent_labels === $child_labels) {
      return $data;
    }
    $processed_data = [];
    foreach ($parent_labels as $parent_label) {
      $processed_data[] = $child_mapped_data[$parent_label] ?? NULL;
    }

    return $processed_data;
  }

  /**
   * Returns the grouping colors for single axis chart type.
   *
   * @param array $data
   *   The data.
   *
   * @return array
   *   The grouping colors.
   */
  private function extractGroupingColorsForSingleAxisChartType(array $data): array {
    if (empty($this->options['grouping'][0]['field'])) {
      return [];
    }

    $grouping_colors = [];
    $grouping_field = $this->options['grouping'][0]['field'];
    $chart_settings = $this->options['chart_settings'];
    $color_selection_method = $chart_settings['fields']['entity_grouping']['color_selection_method'] ?? '';
    $grouping_entity_field = $this->view->field[$grouping_field];
    $group_field_name = $grouping_entity_field ? ($grouping_entity_field->definition['field_name'] ?? '') : '';
    foreach ($data as $index => $set) {
      $row = $this->view->result[$index];
      if ($color_selection_method && $group_field_name && $grouping_entity_field instanceof EntityField && $row instanceof ResultRow) {
        switch ($color_selection_method) {
          case 'by_entities_on_entity_reference':
            $grouping_colors[$index][$set[0]] = $this->extractGroupedSelectedColorByEntity($grouping_entity_field, $row, $group_field_name);
            break;

          case 'by_field_on_referenced_entity':
            $grouping_colors[$index][$set[0]] = $this->extractGroupedSelectedColorOnReferencedEntityField($grouping_entity_field, $row, $group_field_name);
            break;
        }
      }
    }
    return $grouping_colors;
  }

}
