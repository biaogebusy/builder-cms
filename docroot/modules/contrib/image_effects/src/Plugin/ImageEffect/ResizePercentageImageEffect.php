<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;

/**
 * Resize an image by percentage.
 *
 * @ImageEffect(
 *   id = "image_effects_resize_percentage",
 *   label = @Translation("Resize percentage"),
 *   description = @Translation("Resize the image by percentage of its width/height. If only a single dimension is specified, the other dimension will be calculated, maintaining the aspect ratio (scale).")
 * )
 */
class ResizePercentageImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'width' => NULL,
      'height' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_resize_percentage_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['width'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Width'),
      '#default_value' => $this->configuration['width'],
      '#description' => $this->t('Enter a value, and specify if pixels or percent. Leave blank to scale according to new height.'),
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => FALSE,
    ];
    $form['height'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Height'),
      '#default_value' => $this->configuration['height'],
      '#description' => $this->t('Enter a value, and specify if pixels or percent. Leave blank to scale according to new width.'),
      '#size' => 5,
      '#maxlength' => 5,
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['width'] = $form_state->getValue('width');
    $this->configuration['height'] = $form_state->getValue('height');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $width = (bool) $form_state->getValue('width');
    $height = (bool) $form_state->getValue('height');
    if ($width === FALSE && $height === FALSE) {
      $form_state->setError($form, $this->t("Either <em>Width</em> or <em>Height</em> must be specified."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $d = ImageUtility::resizeDimensions($dimensions['width'], $dimensions['height'], $this->configuration['width'], $this->configuration['height']);
    $dimensions['width'] = $d['width'];
    $dimensions['height'] = $d['height'];
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Get resulting dimensions.
    $dimensions = ImageUtility::resizeDimensions($image->getWidth(), $image->getHeight(), $this->configuration['width'], $this->configuration['height']);
    return $image->resize($dimensions['width'], $dimensions['height']);
  }

}
