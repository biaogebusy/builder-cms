<?php

namespace Drupal\image_effects_module_test\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Image effect that relies on a font URI selected via the font selector plugin.
 *
 * @ImageEffect(
 *   id = "image_effects_module_test_font_selection",
 *   label = @Translation("Font selection test image effect")
 * )
 */
class FontSelectionImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The image selector plugin.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface
   */
  protected $fontSelector;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageEffectsFontSelectorPluginInterface $font_selector_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->fontSelector = $font_selector_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('plugin.manager.image_effects.font_selector')->getPlugin()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'font_name' => '',
      'font_uri' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => $this->configuration['font_name'],
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Image selection.
    $options = [
      '#default_value' => $this->configuration['font_uri'],
    ];
    $form['font_uri'] = $this->fontSelector->selectionElement($options);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['font_uri'] = $form_state->getValue('font_uri');
    $this->configuration['font_name'] = $this->fontSelector->getDescription($form_state->getValue('font_uri'));
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return TRUE;
  }

}
