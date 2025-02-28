<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Adjust image transparency.
 *
 * @ImageEffect(
 *   id = "image_effects_opacity",
 *   label = @Translation("Opacity"),
 *   description = @Translation("Change overall image transparency level. Applies only to image formats that support Alpha channel, like PNG.")
 * )
 */
class OpacityImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'opacity' => 50,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_opacity_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#field_suffix' => '%',
      '#description' => $this->t('Opacity: 0 - 100'),
      '#default_value' => $this->configuration['opacity'],
      '#min' => 0,
      '#max' => 100,
      '#maxlength' => 3,
      '#size' => 3,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['opacity'] = $form_state->getValue('opacity');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('opacity', ['opacity' => $this->configuration['opacity']]);
  }

}
