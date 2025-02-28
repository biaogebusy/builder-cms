<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Sharpen image using convolution.
 *
 * @ImageEffect(
 *   id = "image_effects_convolution_sharpen",
 *   label = @Translation("Sharpen"),
 *   description = @Translation("Sharpen image using convolution.")
 * )
 */
class ConvolutionSharpenImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'level' => 10,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_convolution_sharpen_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['level'] = [
      '#type' => 'number',
      '#title' => $this->t('Sharpen level'),
      '#description' => $this->t('Typically 1 - 50.'),
      '#default_value' => $this->configuration['level'],
      '#required' => TRUE,
      '#allow_negative' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['level'] = $form_state->getValue('level');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $sharpenlevel = $this->configuration['level'] / 100;
    $kernel = [
      [-$sharpenlevel, -$sharpenlevel, -$sharpenlevel],
      [-$sharpenlevel, 8 * $sharpenlevel + 1, -$sharpenlevel],
      [-$sharpenlevel, -$sharpenlevel, -$sharpenlevel],
    ];
    return $image->apply('convolution', [
      'kernel' => $kernel,
      'divisor' => 1,
      'offset' => 0,
    ]);
  }

}
