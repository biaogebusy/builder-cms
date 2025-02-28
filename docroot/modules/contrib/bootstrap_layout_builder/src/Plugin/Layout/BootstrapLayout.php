<?php

namespace Drupal\bootstrap_layout_builder\Plugin\Layout;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Render\Element;

/**
 * A layout from our bootstrap layout builder.
 *
 * @Layout(
 *   id = "bootstrap_layout_builder",
 *   deriver = "Drupal\bootstrap_layout_builder\Plugin\Deriver\BootstrapLayoutDeriver"
 * )
 */
class BootstrapLayout extends LayoutDefault implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The styles group plugin manager.
   *
   * @var \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager
   */
  protected $stylesGroupManager;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager $styles_group_manager
   *   The styles group plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, StylesGroupManager $styles_group_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->stylesGroupManager = $styles_group_manager;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.bootstrap_styles_group')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);

    // Row classes and attributes.
    $section_classes = [];
    if ($this->configuration['section_classes']) {
      $section_classes = explode(' ', $this->configuration['section_classes']);
      $build['#attributes']['class'] = $section_classes;
    }

    if (!empty($this->configuration['section_attributes'])) {
      $section_attributes = $this->configuration['section_attributes'];
      $build['#attributes'] = NestedArray::mergeDeep($build['#attributes'] ?? [], $section_attributes);
    }

    // The default one col layout class.
    if (count($this->getPluginDefinition()->getRegionNames()) == 1) {
      $config = $this->configFactory->get('bootstrap_layout_builder.settings');
      $one_col_layout_class = 'col-12';
      if ($config->get('one_col_layout_class')) {
        $one_col_layout_class = $config->get('one_col_layout_class');
      }
      $this->configuration['layout_regions_classes']['blb_region_col_1'][] = $one_col_layout_class;
    }

    // Regions classes and attributes.
    if ($this->configuration['regions_classes']) {
      foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
        $region_classes = $this->configuration['regions_classes'][$region_name];
        if ($this->configuration['layout_regions_classes'] && isset($this->configuration['layout_regions_classes'][$region_name])) {
          $build[$region_name]['#attributes']['class'] = $this->configuration['layout_regions_classes'][$region_name];
        }
        $build[$region_name]['#attributes']['class'][] = $region_classes;
      }
    }

    if ($this->configuration['regions_attributes']) {
      foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
        $region_attributes = $this->configuration['regions_attributes'][$region_name];
        if (!empty($region_attributes)) {
          $build[$region_name]['#attributes'] = NestedArray::mergeDeep($build[$region_name]['#attributes'] ?? [], $region_attributes);
        }
      }
    }

    // Container.
    if ($this->configuration['container']) {
      $theme_wrappers = [
        'blb_container' => [
          '#attributes' => [
            'class' => [$this->configuration['container']],
          ],
        ],
        'blb_container_wrapper' => [
          '#attributes' => [
            'class' => [],
          ],
        ],
      ];

      if ($this->configuration['container_wrapper_classes']) {
        $theme_wrappers['blb_container_wrapper']['#attributes']['class'][] = $this->configuration['container_wrapper_classes'];
      }

      if (!empty($this->configuration['container_wrapper_attributes'])) {
        $wrapper_attributes = $this->configuration['container_wrapper_attributes'];
        $theme_wrappers['blb_container_wrapper']['#attributes'] = NestedArray::mergeDeep($theme_wrappers['blb_container_wrapper']['#attributes'] ?? [], $wrapper_attributes);
      }

      $build['#theme_wrappers'] = $theme_wrappers;

      // Build dynamic styles.
      $build = $this->stylesGroupManager->buildStyles(
        $build,
      // storage.
        $this->configuration['container_wrapper']['bootstrap_styles'],
      // Theme wrapper that we need to apply styles to it.
        'blb_container_wrapper'
      );
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_configuration = parent::defaultConfiguration();

    $regions_classes = $regions_attributes = [];
    foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
      $regions_classes[$region_name] = '';
      $regions_attributes[$region_name] = [];
    }

    return $default_configuration + [
      // Container wrapper commonly used on container background and minor styling.
      'container_wrapper_classes' => '',
      'container_wrapper_attributes' => [],
      // Container wrapper.
      'container_wrapper' => [
        // The dynamic bootstrap styles storage.
        'bootstrap_styles' => [],
      ],
      // Add background color to container wrapper.
      'container_wrapper_bg_color_class' => '',
      // Add background media to container wrapper.
      'container_wrapper_bg_media' => NULL,
      // Container is the section wrapper.
      // Empty means no container else it reflect container type.
      // In bootstrap it will be 'container' or 'container-fluid'.
      'container' => '',
      // Section refer to the div that contains row in bootstrap.
      'section_classes' => '',
      'section_attributes' => [],
      // Region refer to the div that contains Col in bootstrap "Advanced mode".
      'regions_classes' => $regions_classes,
      'regions_attributes' => $regions_attributes,
      // Array of breakpoints and the value of its option.
      'breakpoints' => [],
      // The region refer to the div that contains Col in bootstrap.
      'layout_regions_classes' => [],
    ];
  }

  /**
   * Helper function to get section settings show/hide status.
   *
   * @return bool
   *   Section settings status.
   */
  public function sectionSettingsIsHidden() {
    $config = $this->configFactory->get('bootstrap_layout_builder.settings');
    $hide_section_settings = FALSE;
    if ($config->get('hide_section_settings')) {
      $hide_section_settings = (bool) $config->get('hide_section_settings');
    }
    return $hide_section_settings;
  }

  /**
   * Helper function to get live preview status.
   *
   * @return bool
   *   Live preview status.
   */
  public function livePreviewIsEnabled() {
    $config = $this->configFactory->get('bootstrap_layout_builder.settings');
    $live_preview = FALSE;
    if ($config->get('live_preview')) {
      $live_preview = (bool) $config->get('live_preview');
    }
    return $live_preview;
  }

  /**
   * Helper function to get the options of given style name.
   *
   * @param string $name
   *   A config style name like background_color.
   *
   * @return array
   *   Array of key => value of style name options.
   */
  public function getStyleOptions(string $name) {
    $config = $this->configFactory->get('bootstrap_layout_builder.settings');
    $options = [];
    $config_options = $config->get($name);

    $options = ['_none' => $this->t('N/A')];
    $lines = explode(PHP_EOL, $config_options);
    foreach ($lines as $line) {
      $line = explode('|', $line);
      if ($line && isset($line[0]) && isset($line[1])) {
        $options[$line[0]] = $line[1];
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Our main set of tabs.
    $form['ui'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'id' => 'bs_ui',
      ],
    ];

    $tabs = [
      [
        'machine_name' => 'layout',
        'icon' => 'layout.svg',
        'title' => $this->t('Layout'),
        'active' => TRUE,
      ],
      [
        'machine_name' => 'appearance',
        'icon' => 'appearance.svg',
        'title' => $this->t('Style'),
      ],
      [
        'machine_name' => 'settings',
        'icon' => 'settings.svg',
        'title' => $this->t('Settings'),
      ],
    ];

    // Create our tabs from above.
    $form['ui']['nav_tabs'] = [
      '#type' => 'html_tag',
      '#tag' => 'ul',
      '#attributes' => [
        'class' => 'bs_nav-tabs',
        'id' => 'bs_nav-tabs',
        'role' => 'tablist',
      ],
    ];

    $form['ui']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'bs_tab-content',
        'id' => 'bs_tabContent',
      ],
    ];

    // Create our tab & tab panes.
    foreach ($tabs as $tab) {
      $form['ui']['nav_tabs'][$tab['machine_name']] = [
        '#type' => 'inline_template',
        '#template' => '<li><a data-target="{{ target|clean_class }}" class="{{active}}"><span role="img">{% include icon %}</span><div class="bs_tooltip" data-placement="bottom" role="tooltip">{{ title }}</div></a></li>',
        '#context' => [
          'title' => $tab['title'],
          'target' => $tab['machine_name'],
          'active' => isset($tab['active']) && $tab['active'] == TRUE ? 'active' : '',
          'icon' => \Drupal::service('extension.list.module')->getPath('bootstrap_styles') . '/images/ui/' . ($tab['icon'] ? $tab['icon'] : 'default.svg'),
        ],
      ];

      $form['ui']['tab_content'][$tab['machine_name']] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'bs_tab-pane',
            'bs_tab-pane--' . $tab['machine_name'],
            isset($tab['active']) && $tab['active'] == TRUE ? 'active' : '',
          ],
        ],
      ];
    }

    $container_types = [
      'container' => $this->t('Boxed'),
      'container-fluid' => $this->t('Full'),
      'w-100' => $this->t('Edge to Edge'),
    ];

    $form['ui']['tab_content']['layout']['container_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Container type'),
      '#options' => $container_types,
      '#default_value' => !empty($this->configuration['container']) ? $this->configuration['container'] : 'container',
      '#attributes' => [
        'class' => ['blb_container_type'],
      ],
    ];

    // Add icons to the container types.
    foreach ($form['ui']['tab_content']['layout']['container_type']['#options'] as $key => $value) {
      $form['ui']['tab_content']['layout']['container_type']['#options'][$key] = '<span class="input-icon ' . $key . '"></span>' . $value;
    }

    $gutter_types = [
      0 => $this->t('With Gutters'),
      1 => $this->t('No Gutters'),
    ];

    $form['ui']['tab_content']['layout']['remove_gutters'] = [
      '#type' => 'radios',
      '#title' => $this->t('Gutters'),
      '#options' => $gutter_types,
      '#default_value' => (int) !empty($this->configuration['remove_gutters']) ? 1 : 0,
      '#attributes' => [
        'class' => ['blb_gutter_type'],
      ],
    ];

    // Add icons to the gutter types.
    foreach ($form['ui']['tab_content']['layout']['remove_gutters']['#options'] as $key => $value) {
      $form['ui']['tab_content']['layout']['remove_gutters']['#options'][$key] = '<span class="input-icon gutter-icon-' . $key . '"></span>' . $value;
    }

    $layout_id = $this->getPluginDefinition()->id();
    $breakpoints = $this->entityTypeManager->getStorage('blb_breakpoint')->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    foreach ($breakpoints as $breakpoint_id) {
      $breakpoint = $this->entityTypeManager->getStorage('blb_breakpoint')->load($breakpoint_id);
      $layout_options = $breakpoint->getLayoutOptions($layout_id);
      if ($layout_options) {
        $options = $this->entityTypeManager->getStorage('blb_layout_option')->loadByProperties(['layout_id' => $layout_id]);
        $default_value = NULL;
        if ($this->configuration['breakpoints'] && isset($this->configuration['breakpoints'][$breakpoint_id])) {
          $default_value = $this->configuration['breakpoints'][$breakpoint_id];
        }
        else {
          $options = $this->entityTypeManager->getStorage('blb_layout_option')->loadByProperties(['layout_id' => $layout_id]);
          foreach ($options as $layoutOption) {
            if (array_search($breakpoint->id(), $layoutOption->getDefaultBreakpointsIds()) !== FALSE) {
              $default_value = $layoutOption->getStructureId();
            }
          }
        }
        $form['ui']['tab_content']['layout']['breakpoints'][$breakpoint_id] = [
          '#type' => 'radios',
          '#title' => $breakpoint->label(),
          '#options' => $layout_options,
          '#default_value' => $default_value,
          '#validated' => TRUE,
          '#attributes' => [
            'class' => ['blb_breakpoint_cols'],
          ],
        ];

        // Check if the live preview enabled.
        if ($this->livePreviewIsEnabled()) {
          $form['ui']['tab_content']['layout']['breakpoints'][$breakpoint_id]['#ajax']['callback'] = [$this, 'livePreviewCallback'];
          $form['ui']['tab_content']['layout']['breakpoints'][$breakpoint_id]['#ajax']['event'] = 'click';
          $form['ui']['tab_content']['layout']['breakpoints'][$breakpoint_id]['#ajax']['progress'] = ['type' => 'none'];
        }
      }
    }

    // Container wrapper styling.
    $form['ui']['tab_content']['appearance'] = $this->stylesGroupManager->buildStylesFormElements($form['ui']['tab_content']['appearance'], $form_state, $this->configuration['container_wrapper']['bootstrap_styles'], 'bootstrap_layout_builder.styles');

    // Move default admin label input to setting tab.
    $form['ui']['tab_content']['settings']['label'] = $form['label'];
    unset($form['label']);

    // Advanced Settings.
    if (!$this->sectionSettingsIsHidden()) {
      $form['ui']['tab_content']['settings']['container'] = [
        '#type' => 'details',
        '#title' => $this->t('Container Settings'),
        '#open' => FALSE,
      ];

      $form['ui']['tab_content']['settings']['container']['container_wrapper_classes'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Container wrapper classes'),
        '#description' => $this->t('Add classes separated by space. Ex: bg-warning py-5.'),
        '#default_value' => $this->configuration['container_wrapper_classes'],
      ];

      $container_attributes = $this->configuration['container_wrapper_attributes'];
      $form['ui']['tab_content']['settings']['container']['container_wrapper_attributes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Container wrapper attributes (YAML)'),
        '#default_value' => empty($container_attributes) ? '' : Yaml::encode($container_attributes),
        '#attributes' => ['class' => ['blb-auto-size']],
        '#rows' => 1,
        '#element_validate' => [[$this, 'validateYaml']],
      ];

      $form['ui']['tab_content']['settings']['row'] = [
        '#type' => 'details',
        '#title' => $this->t('Row Settings'),
        '#description' => $this->t('Add classes separated by space. Ex: col mb-5 py-3.'),
        '#open' => FALSE,
      ];

      $form['ui']['tab_content']['settings']['row']['section_classes'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Row classes'),
        '#description' => $this->t('Row has "row" class, you can add more classes separated by space. Ex: no-gutters py-3.'),
        '#default_value' => $this->configuration['section_classes'],
      ];

      $row_attributes = $this->configuration['section_attributes'];
      $form['ui']['tab_content']['settings']['row']['section_attributes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Row attributes (YAML)'),
        '#default_value' => empty($row_attributes) ? '' : Yaml::encode($row_attributes),
        '#attributes' => ['class' => ['auto-size']],
        '#rows' => 1,
        '#element_validate' => [[$this, 'validateYaml']],
      ];

      $form['ui']['tab_content']['settings']['regions'] = [
        '#type' => 'details',
        '#title' => $this->t('Columns Settings'),
        '#description' => $this->t('Add classes separated by space. Ex: col mb-5 py-3.'),
        '#open' => FALSE,
      ];

      foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
        $form['ui']['tab_content']['settings']['regions'][$region_name . '_classes'] = [
          '#type' => 'textfield',
          '#title' => $this->getPluginDefinition()->getRegionLabels()[$region_name] . ' ' . $this->t('classes'),
          '#default_value' => $this->configuration['regions_classes'][$region_name],
        ];

        $region_attributes = $this->configuration['regions_attributes'][$region_name];
        $form['ui']['tab_content']['settings']['regions'][$region_name . '_attributes'] = [
          '#type' => 'textarea',
          '#title' => $this->getPluginDefinition()->getRegionLabels()[$region_name] . ' ' . $this->t('attributes (YAML)'),
          '#default_value' => empty($region_attributes) ? '' : Yaml::encode($region_attributes),
          '#attributes' => ['class' => ['auto-size']],
          '#rows' => 1,
          '#element_validate' => [[$this, 'validateYaml']],
        ];

      }
    }

    // Check if the live preview enabled.
    if ($this->livePreviewIsEnabled()) {
      // Add the ajax live preview to form elements.
      $this->addAjaxLivePreviewToElement($form['ui']['tab_content']);
    }

    // Attach Bootstrap Styles base library.
    $form['#attached']['library'][] = 'bootstrap_styles/layout_builder_form_style';

    // Attach the Bootstrap Layout Builder base library.
    $form['#attached']['library'][] = 'bootstrap_layout_builder/layout_builder_form_style';

    return $form;
  }

  /**
   * Add live preview to element.
   * 
   * @param array $element
   *   The target element.
   */
  public function addAjaxLivePreviewToElement(array &$element) {
    $types = [
      'radios',
      'radio',
      'checkbox',
      'textfield',
      'textarea',
      'range',
    ];

    if (!isset($element['#type'])) {
      return;
    }

    if (in_array($element['#type'], $types) && !isset($element['#ajax']) && !isset($element['#disable_live_preview'])) {
      $element['#ajax']['callback'] = [$this, 'livePreviewCallback'];
      $element['#ajax']['event'] = 'change';
      $element['#ajax']['progress'] = ['type' => 'none'];
    }

    if (Element::children($element)) {
      foreach (Element::children($element) as $key) {
        $this->addAjaxLivePreviewToElement($element[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function livePreviewCallback(array $form, FormStateInterface $form_state) {
    $form_state->getFormObject()->submitForm($form, $form_state);
    $layout = [
      '#type' => 'layout_builder',
      '#section_storage' => $form_state->getFormObject()->getSectionStorage(),
    ];

    $data = [];
    $tempstore = \Drupal::service('tempstore.private')->get('bootstrap_styles');
    $data['active_device'] = $tempstore->get('active_device');

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#layout-builder', $layout));

    return $response;
  }

  /**
   * Returns region class of a breakpoint.
   *
   * @param int $key
   *   The position of region.
   * @param array $breakpoints
   *   The layout active breakpoints.
   *
   * @return array
   *   The region classes of all breakpoints.
   */
  public function getRegionClasses(int $key, array $breakpoints) {
    $classes = [];
    foreach ($breakpoints as $breakpoint_id => $strucutre_id) {
      $breakpoint = $this->entityTypeManager->getStorage('blb_breakpoint')->load($breakpoint_id);
      $classes[] = $breakpoint->getClassByPosition($key, $strucutre_id);
    }
    return $classes;
  }

  /**
   * Save breakpoints to the configuration.
   *
   * @param array $breakpoints
   *   The layout active breakpoints.
   */
  public function saveBreakpoints(array $breakpoints) {
    $this->configuration['breakpoints'] = $breakpoints;
  }

  /**
   * {@inheritdoc}
   */
  public function validateYaml($element, FormStateInterface $form_state, array $form) {
    $value = $element['#value'];
    try {
      $array_values = Yaml::decode($value);

      // Fix Classes as strings.
      if (isset($array_values['class']) && !is_array($array_values['class'])) {
        $array_values['class'] = explode(' ', $array_values['class']);
      }
      $form_state->setValueForElement($element, Yaml::encode($array_values));
    }
    catch (\Exception $exception) {
      $form_state->setError($element, $this->t('Invalid YAML entered for %field', ['%field' => $element['#title']]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    // The tabs structure.
    $layout_tab = ['ui', 'tab_content', 'layout'];
    $style_tab = ['ui', 'tab_content', 'appearance'];
    $settings_tab = ['ui', 'tab_content', 'settings'];

    // Save section label.
    $this->configuration['label'] = $form_state->getValue(array_merge($settings_tab, ['label']));

    // Container type.
    $this->configuration['container'] = $form_state->getValue(array_merge($layout_tab, ['container_type']));

    // Styles tab.
    $this->configuration['container_wrapper']['bootstrap_styles'] = $this->stylesGroupManager->submitStylesFormElements($form['ui']['tab_content']['appearance'], $form_state, $style_tab, $this->configuration['container_wrapper']['bootstrap_styles'], 'bootstrap_layout_builder.styles');

    // Container classes from advanced mode.
    if (!$this->sectionSettingsIsHidden()) {
      $this->configuration['container_wrapper_classes'] = $form_state->getValue(array_merge($settings_tab, ['container', 'container_wrapper_classes']));
      $this->configuration['container_wrapper_attributes'] = Yaml::decode($form_state->getValue(array_merge($settings_tab, ['container', 'container_wrapper_attributes'])));
    }

    // Gutter Classes.
    $this->configuration['remove_gutters'] = $form_state->getValue(array_merge($layout_tab, ['remove_gutters']));

    // Row classes from advanced mode.
    if (!$this->sectionSettingsIsHidden()) {
      $this->configuration['section_classes'] = $form_state->getValue(array_merge($settings_tab, ['row', 'section_classes']));
      $this->configuration['section_attributes'] = Yaml::decode($form_state->getValue(array_merge($settings_tab, ['row', 'section_attributes'])));
    }

    $breakpoints = $form_state->getValue(array_merge($layout_tab, ['breakpoints']));
    // Save breakpoints configuration.
    if ($breakpoints) {
      $this->saveBreakpoints($breakpoints);
      foreach ($this->getPluginDefinition()->getRegionNames() as $key => $region_name) {
        // Save layout region classes.
        $this->configuration['layout_regions_classes'][$region_name] = $this->getRegionClasses($key, $breakpoints);
        // Cols classes from advanced mode.
        if (!$this->sectionSettingsIsHidden()) {
          $this->configuration['regions_classes'][$region_name] = $form_state->getValue(array_merge($settings_tab, ['regions', $region_name . '_classes']));
          $this->configuration['regions_attributes'][$region_name] = Yaml::decode($form_state->getValue(array_merge($settings_tab, ['regions', $region_name . '_attributes'])));
        }
      }
    }
    else {
      foreach ($this->getPluginDefinition()->getRegionNames() as $key => $region_name) {
        // Cols classes from advanced mode.
        if (!$this->sectionSettingsIsHidden()) {
          $this->configuration['regions_classes'][$region_name] = $form_state->getValue(array_merge($settings_tab,['regions', $region_name . '_classes']));
          $this->configuration['regions_attributes'][$region_name] = Yaml::decode($form_state->getValue(array_merge($settings_tab,['regions', $region_name . '_attributes'])));
        }
      }
    }
  }

}
