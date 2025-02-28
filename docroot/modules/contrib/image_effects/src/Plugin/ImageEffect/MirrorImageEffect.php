<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Mirror image.
 *
 * @ImageEffect(
 *   id = "image_effects_mirror",
 *   label = @Translation("Mirror"),
 *   description = @Translation("Mirror the image horizontally and/or vertically.")
 * )
 */
class MirrorImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'x_axis' => FALSE,
      'y_axis' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_mirror_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['x_axis'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Horizontal'),
      '#description' => $this->t("If checked, the source image will be 'flopped' horizontally."),
      '#default_value' => $this->configuration['x_axis'],
    ];
    $form['y_axis'] = [
      '#type'  => 'checkbox',
      '#title' => $this->t('Vertical'),
      '#description' => $this->t("If checked, the source image will be 'flipped' vertically."),
      '#default_value' => $this->configuration['y_axis'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $x_axis = (bool) $form_state->getValue('x_axis');
    $y_axis = (bool) $form_state->getValue('y_axis');
    if ($x_axis === FALSE && $y_axis === FALSE) {
      $form_state->setError($form, $this->t("Either an Horizontal or a Vertical mirroring must be selected."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['x_axis'] = (bool) $form_state->getValue('x_axis');
    $this->configuration['y_axis'] = (bool) $form_state->getValue('y_axis');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('mirror', [
      'x_axis' => $this->configuration['x_axis'],
      'y_axis' => $this->configuration['y_axis'],
    ]);
  }

}
