<?php

declare(strict_types=1);

namespace Drupal\image_effects\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image_effects\Plugin\ColorSelectorPluginManager;
use Drupal\image_effects\Plugin\FontSelectorPluginManager;
use Drupal\image_effects\Plugin\ImageSelectorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main image_effects settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ColorSelectorPluginManager $colorManager,
    protected ImageSelectorPluginManager $imageManager,
    protected FontSelectorPluginManager $fontManager,
  ) {
    parent::__construct(
      $configFactory,
      $typedConfigManager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(TypedConfigManagerInterface::class),
      $container->get(ColorSelectorPluginManager::class),
      $container->get(ImageSelectorPluginManager::class),
      $container->get(FontSelectorPluginManager::class),
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
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $color_plugin */
    $color_plugin = $this->colorManager->getPlugin($color_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }

    // Image selector plugin.
    $image_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'image_selector', 'plugin_id']) : $config->get('image_selector.plugin_id');
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $image_plugin */
    $image_plugin = $this->imageManager->getPlugin($image_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'image_selector', 'plugin_settings'])) {
      $image_plugin->setConfiguration($form_state->getValue(['settings', 'image_selector', 'plugin_settings']));
    }

    // Font selector plugin.
    $font_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'font_selector', 'plugin_id']) : $config->get('font_selector.plugin_id');
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $font_plugin */
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
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $color_plugin */
    $color_plugin = $this->colorManager->getPlugin($form_state->getValue(['settings', 'color_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'color_selector', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color_selector', 'plugin_settings']));
    }
    $config
      ->set('color_selector.plugin_id', $color_plugin->getPluginId())
      ->set('color_selector.plugin_settings.' . $color_plugin->getPluginId(), $color_plugin->getConfiguration());

    // Image plugin.
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $image_plugin */
    $image_plugin = $this->imageManager->getPlugin($form_state->getValue(['settings', 'image_selector', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'image_selector', 'plugin_settings'])) {
      $image_plugin->setConfiguration($form_state->getValue(['settings', 'image_selector', 'plugin_settings']));
    }
    $config
      ->set('image_selector.plugin_id', $image_plugin->getPluginId())
      ->set('image_selector.plugin_settings.' . $image_plugin->getPluginId(), $image_plugin->getConfiguration());

    // Font plugin.
    /** @var \Drupal\image_effects\Plugin\ImageEffectsPluginBase $font_plugin */
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
  public function processAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $status_messages = ['#type' => 'status_messages'];
    $response->addCommand(new HtmlCommand('#image-effects-ajax-messages', $status_messages));
    $response->addCommand(new HtmlCommand('#image-effects-settings-main', $form['settings']));
    return $response;
  }

}
