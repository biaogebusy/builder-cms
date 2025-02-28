<?php

namespace Drupal\bootstrap_styles\StylesGroup;

use Drupal\Component\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\bootstrap_styles\HelperTrait;

/**
 * A base class to help developers implement their own Styles Group plugins.
 */
abstract class StylesGroupPluginBase extends PluginBase implements StylesGroupPluginInterface, ContainerFactoryPluginInterface {
  use StringTranslationTrait, HelperTrait;

  /**
   * Config settings.
   *
   * @var string
   */
  const CONFIG = 'bootstrap_styles.settings';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a StylePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPath() {
    // The default icon.
    $icon_path = \Drupal::service('extension.list.module')->getPath('bootstrap_styles') . '/images/plugins/plugin-group-default-icon.svg';
    if (isset($this->pluginDefinition['icon'])) {
      $icon = $this->pluginDefinition['icon'];
      $path_array = explode('/', $icon ?? '');
      if (isset($path_array[0])) {
        $module_name = $path_array[0];
        $icon_path = \Drupal::service('extension.list.module')->getPath($module_name) . str_replace($module_name, '', $icon);
      }
    }
    return $icon_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleWithIcon() {
    return [
      '#markup' => $this->getSvgIconMarkup($this->getIconPath()),
      '#prefix' => '<span class="bs-group-title">',
      '#suffix' => $this->getTitle() . '</span>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->configFactory->getEditable(static::CONFIG);
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
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    return $build;
  }

}
