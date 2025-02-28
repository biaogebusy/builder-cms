<?php

namespace Drupal\image_effects_module_test\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test effect that uses the image selector plugin to get an image.
 *
 * @ImageEffect(
 *   id = "image_effects_module_test_image_selection",
 *   label = @Translation("Image selection test image effect")
 * )
 */
class ImageSelectionImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The image selector plugin.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface
   */
  protected $imageSelector;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageEffectsPluginBaseInterface $image_selector_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->imageSelector = $image_selector_plugin;
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
      $container->get('plugin.manager.image_effects.image_selector')->getPlugin()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'image_uri' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => $this->configuration['image_uri'],
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Image selection.
    $options = [
      '#default_value' => $this->configuration['image_uri'],
    ];
    $form['image_uri'] = $this->imageSelector->selectionElement($options);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['image_uri'] = $form_state->getValue('image_uri');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return TRUE;
  }

}
