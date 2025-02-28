<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Pixelate the image.
 *
 * @ImageEffect(
 *   id = "image_effects_pixelate",
 *   label = @Translation("Pixelate"),
 *   description = @Translation("Pixelate the image.")
 * )
 */
class PixelateImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'size' => 10,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_pixelate_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size of the pixels'),
      '#default_value' => $this->configuration['size'],
      '#field_suffix' => $this->t('px'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['size'] = $form_state->getValue('size');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('pixelate', $this->configuration);
  }

}
