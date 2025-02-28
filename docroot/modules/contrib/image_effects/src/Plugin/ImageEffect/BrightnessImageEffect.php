<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Adjust image brightness.
 *
 * @ImageEffect(
 *   id = "image_effects_brightness",
 *   label = @Translation("Brightness"),
 *   description = @Translation("Adjust image brightness.")
 * )
 */
class BrightnessImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'level' => 0,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_brightness_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['level'] = [
      '#type' => 'number',
      '#title' => $this->t('Brightness'),
      '#description'  => $this->t('The brightness effect seldom looks good on its own, but can be useful to wash out an image before making it transparent - e.g. for a watermark.'),
      '#default_value' => $this->configuration['level'],
      '#field_prefix' => $this->t('±'),
      '#field_suffix' => $this->t('%'),
      '#required' => TRUE,
      '#size' => 5,
      '#min' => -100,
      '#max' => 100,
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
    return $image->apply('brightness', ['level' => $this->configuration['level']]);
  }

}
