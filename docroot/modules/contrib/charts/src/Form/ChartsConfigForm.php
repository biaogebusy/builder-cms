<?php

namespace Drupal\charts\Form;

use Drupal\charts\ChartManager;
use Drupal\charts\DependenciesCalculatorTrait;
use Drupal\charts\TypeManager;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Charts Config Form.
 */
class ChartsConfigForm extends ConfigFormBase {

  use DependenciesCalculatorTrait;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The chart library plugin manager.
   *
   * @var \Drupal\charts\ChartManager
   */
  protected $chartPluginManager;

  /**
   * The chart type plugin library manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected $chartTypePluginManager;

  /**
   * The module extension service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a new ChartsConfigForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   Cache tag invalidator.
   * @param \Drupal\charts\ChartManager|null $chart_plugin_manager
   *   The chart plugin manager.
   * @param \Drupal\charts\TypeManager|null $chart_type_plugin_manager
   *   The chart type plugin manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList|null $module_extension_list
   *   The module extension list.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheTagsInvalidatorInterface $cache_tags_invalidator, ChartManager $chart_plugin_manager = NULL, TypeManager $chart_type_plugin_manager = NULL, ModuleExtensionList $module_extension_list = NULL) {
    parent::__construct($config_factory);
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    // @todo Implement full if statement for optional parameters, and add
    // deprecation warnings when they are not passed.
    $this->chartPluginManager = $chart_plugin_manager ?: \Drupal::service('plugin.manager.charts');
    $this->chartTypePluginManager = $chart_type_plugin_manager ?: \Drupal::service('plugin.manager.charts_type');
    $this->moduleExtensionList = $module_extension_list ?: \Drupal::service('extension.list.module');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_tags.invalidator'),
      $container->get('plugin.manager.charts'),
      $container->get('plugin.manager.charts_type'),
      $container->get('extension.list.module')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'charts_form_base';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['charts.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_config = $this->config('charts.settings')->get('charts_default_settings') ?: [];

    $form['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The settings on this page are used to set
        <strong>default</strong> settings. They do not affect existing charts.
        To make a new chart, create a new view and select the display format of
        "Chart." Or use a Charts Block and add your own data inside that block.
        You can also attach a Chart field to your content (or other entity)
        type and add your data within the Chart field.'),
    ];
    $form['settings'] = [
      '#type' => 'charts_settings',
      '#used_in' => 'config_form',
      '#required' => TRUE,
      '#default_value' => $default_config,
    ];

    $form['actions']['reset_to_default'] = [
      '#type' => 'submit',
      '#submit' => ['::submitReset'],
      '#value' => $this->t('Reset to default configurations'),
      '#weight' => 100,
      '#access' => !empty($default_config['library']),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('settings');
    if (empty($settings['library'])) {
      $form_state->setError($form['settings'], $this->t('Please select a library to use by default or install a module implementing a chart library plugin.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('settings');
    // The settings form element is returning an unneeded 'defaults' value.
    if (isset($settings['defaults'])) {
      unset($settings['defaults']);
    }

    // Process the default colors to remove unneeded data.
    foreach ($settings['display']['colors'] as $color_index => $color_item) {
      $settings['display']['colors'][$color_index] = $color_item['color'];
    }

    // Save the main settings.
    $config = $this->config('charts.settings');
    $config->set('dependencies', $this->calculateDependencies($settings['library'], $settings['type']))
      ->set('charts_default_settings', $settings)
      ->save();

    // Invalidate cache tags to refresh any view relying on this.
    $this->cacheTagsInvalidator->invalidateTags($config->getCacheTags());

    parent::submitForm($form, $form_state);
  }

  /**
   * Reset submit callback.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitReset(array &$form, FormStateInterface $form_state) {
    $path = $this->moduleExtensionList->getPath('charts');
    $default_install_settings_file = $path . '/config/install/charts.settings.yml';
    if (!file_exists($default_install_settings_file)) {
      $this->messenger()->addWarning($this->t('We could not reset the configuration to default because the default settings file does not exist. Please re-download the charts module files.'));
      return;
    }

    $config = $this->config('charts.settings');
    $default_install_settings = Yaml::decode(file_get_contents($default_install_settings_file));
    $config->set('charts_default_settings', $default_install_settings['charts_default_settings'])
      ->set('dependencies', $default_install_settings['dependencies'])
      ->save();

    $this->messenger()->addStatus($this->t('The charts configuration were successfully reset to default.'));
  }

}
