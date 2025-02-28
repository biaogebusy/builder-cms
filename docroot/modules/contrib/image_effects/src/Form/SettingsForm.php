<?php
// @codingStandardsIgnoreFile

namespace Drupal\image_effects\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image_effects\Plugin\ImageEffectsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main image_effects settings admin form.
 *
 * @todo the array syntax for $form_state->getValue([...]) fails code style
 * checking, but this is quite inconvenient. See if sniff gets adjusted or
 * a different way to access nested keys will be available.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The color selector plugin manager.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginManager
   */
  protected $colorManager;

  /**
   * The image selector plugin manager.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginManager
   */
  protected $imageManager;

  /**
   * The font selector plugin manager.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginManager
   */
  protected $fontManager;

  /**
   * Constructs the class for image_effects settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\image_effects\Plugin\ImageEffectsPluginManager $color_plugin_manager
   *   The color selector plugin manager.
   * @param \Drupal\image_effects\Plugin\ImageEffectsPluginManager $image_plugin_manager
   *   The image selector plugin manager.
   * @param \Drupal\image_effects\Plugin\ImageEffectsPluginManager $font_plugin_manager
   *   The font selector plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ImageEffectsPluginManager $color_plugin_manager, ImageEffectsPluginManager $image_plugin_manager, ImageEffectsPluginManager $font_plugin_manager) {
    parent::__construct($config_factory);
    $this->colorManager = $color_plugin_manager;
    $this->imageManager = $image_plugin_manager;
    $this->fontManager = $font_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.image_effects.color_selector'),
      $container->get('plugin.manager.image_effects.image_selector'),
      $container->get('plugin.manager.image_effects.font_selector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'image_effects_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['image_effects.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('image_effects.settings');

    $ajaxing = (bool) $form_state->getValues();

    // Color selector plugin.
    $color_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'color_selector', 'plugin_id']) : $config->get('color_selector.plugin_id');
    $color_plugin = $this->colorManager->getPlugin($color_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }

    // Image selector plugin.
    $image_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'image_selector', 'plugin_id']) : $config->get('image_selector.plugin_id');
    $image_plugin = $this->imageManager->getPlugin($image_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'image_selector', 'plugin_settings'])) {
      $image_plugin->setConfiguration($form_state->getValue(['settings', 'image_selector', 'plugin_settings']));
    }

    // Font selector plugin.
    $font_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'font_selector', 'plugin_id']) : $config->get('font_selector.plugin_id');
    $font_plugin = $this->fontManager->getPlugin($font_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'font_selector', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font_selector', 'plugin_settings']));
    }

    // AJAX messages.
    $form['ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'image-effects-ajax-messages',
      ],
    ];

    // AJAX settings.
    $ajax_settings = ['callback' => [$this, 'processAjax']];

    // Main part of settings form.
    $form['settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'image-effects-settings-main',
      ],
    ];

    // Color selector.
    $form['settings']['color_selector'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Color selector'),
      '#tree' => TRUE,
    ];
    $form['settings']['color_selector']['plugin_id'] = [
      '#type' => 'radios',
      '#options' => $this->colorManager->getPluginOptions(),
      '#default_value' => $color_plugin->getPluginId(),
      '#required' => TRUE,
      '#ajax'  => $ajax_settings,
    ];
    $form['settings']['color_selector']['plugin_settings'] = $color_plugin->buildConfigurationForm([], $form_state, $ajax_settings);

    // Image selector.
    $form['settings']['image_selector'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Image selector'),
      '#tree' => TRUE,
    ];
    $form['settings']['image_selector']['plugin_id'] = [
      '#type'    => 'radios',
      '#options' => $this->imageManager->getPluginOptions(),
      '#default_value' => $image_plugin->getPluginId(),
      '#required'    => TRUE,
      '#ajax'  => $ajax_settings,
    ];
    $form['settings']['image_selector']['plugin_settings'] = $image_plugin->buildConfigurationForm([], $form_state, $ajax_settings);

    // Font selector.
    $form['settings']['font_selector'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Font selector'),
      '#tree' => TRUE,
    ];
    $form['settings']['font_selector']['plugin_id'] = [
      '#type'    => 'radios',
      '#options' => $this->fontManager->getPluginOptions(),
      '#default_value' => $font_plugin->getPluginId(),
      '#required'    => TRUE,
      '#ajax'  => $ajax_settings,
    ];
    $form['settings']['font_selector']['plugin_settings'] = $font_plugin->buildConfigurationForm([], $form_state, $ajax_settings);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('image_effects.settings');

    // Color plugin.
    $color_plugin = $this->colorManager->getPlugin($form_state->getValue(['settings', 'color_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }
    $config
      ->set('color_selector.plugin_id', $color_plugin->getPluginId())
      ->set('color_selector.plugin_settings.' . $color_plugin->getPluginId(), $color_plugin->getConfiguration());

    // Image plugin.
    $image_plugin = $this->imageManager->getPlugin($form_state->getValue(['settings', 'image_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'image_selector', 'plugin_settings'])) {
      $image_plugin->setConfiguration($form_state->getValue(['settings', 'image_selector', 'plugin_settings']));
    }
    $config
      ->set('image_selector.plugin_id', $image_plugin->getPluginId())
      ->set('image_selector.plugin_settings.' . $image_plugin->getPluginId(), $image_plugin->getConfiguration());

    // Font plugin.
    $font_plugin = $this->fontManager->getPlugin($form_state->getValue(['settings', 'font_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'font_selector', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font_selector', 'plugin_settings']));
    }
    $config
      ->set('font_selector.plugin_id', $font_plugin->getPluginId())
      ->set('font_selector.plugin_settings.' . $font_plugin->getPluginId(), $font_plugin->getConfiguration());

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback.
   */
  public function processAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $status_messages = ['#type' => 'status_messages'];
    $response->addCommand(new HtmlCommand('#image-effects-ajax-messages', $status_messages));
    $response->addCommand(new HtmlCommand('#image-effects-settings-main', $form['settings']));
    return $response;
  }

}
