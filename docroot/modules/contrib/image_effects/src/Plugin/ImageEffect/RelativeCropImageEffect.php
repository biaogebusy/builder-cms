<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\Plugin\ImageEffect\CropImageEffect;
use Drupal\image_effects\Component\ImageUtility;

/**
 * Provides an image effect that crops images to a ratio.
 *
 * @ImageEffect(
 *   id = "image_effects_relative_crop",
 *   label = @Translation("Relative crop"),
 *   description = @Translation("Resizing will make images match a ratio, for example 4:3 or 16:9. Images that are wider than the ratio will be cropped in width, images that are higher than the ratio will be cropped in height."),
 * )
 */
class RelativeCropImageEffect extends CropImageEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $dimensions = [
      'width' => $image->getWidth(),
      'height' => $image->getHeight(),
    ];

    // Bail if the image is invalid.
    if (($dimensions['width'] === NULL) || ($dimensions['height'] === NULL)) {
      return FALSE;
    }

    $original_dimensions = $dimensions;
    $this->transformDimensions($dimensions, $image->getSource());

    // Pick the right anchor depending on whether the image is being cropped in
    // width or in height.
    if ($dimensions['width'] !== $original_dimensions['width']) {
      $x = ImageUtility::getKeywordOffset($this->configuration['anchor']['width'], $original_dimensions['width'], $dimensions['width']);
      $y = 0;
    }
    elseif ($dimensions['height'] !== $original_dimensions['height']) {
      $x = 0;
      $y = ImageUtility::getKeywordOffset($this->configuration['anchor']['height'], $original_dimensions['height'], $dimensions['height']);
    }
    else {
      // If the image already has the correct dimensions, do not do anything.
      return TRUE;
    }

    if (!$image->crop($x, $y, $dimensions['width'], $dimensions['height'])) {
      $this->logger->error('Image crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
        '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    if (!$this->configuration['width'] || !$this->configuration['height']) {
      // If the effect has not been configured, there is nothing we can do.
      return;
    }
    $ratio = $this->configuration['width'] / $this->configuration['height'];

    // Figure out whether the image is too wide or too high.
    $ratio_width = (int) round($dimensions['height'] * $ratio);
    if ($dimensions['width'] > $ratio_width) {
      $dimensions['width'] = $ratio_width;
    }
    elseif ($dimensions['width'] < $ratio_width) {
      // Instead of it being too narrow we consider the image as being too tall.
      $dimensions['height'] = (int) round($dimensions['width'] / $ratio);
    }
    // If the image already fits the ratio, do not change the dimensions.
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_relative_crop_summary',
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'anchor' => [
        'width' => 'center',
        'height' => 'center',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['width']['#field_suffix']);
    unset($form['height']['#field_suffix']);

    $form['anchor'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Anchor'),
    ];
    $form['anchor']['width'] = [
      '#type' => 'select',
      '#title' => $this->t('Width'),
      '#options' => [
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $this->configuration['anchor']['width'],
      '#description' => $this->t('The anchor that will be used to crop images that are wider than the configured ratio.'),
    ];
    $form['anchor']['height'] = [
      '#type' => 'select',
      '#title' => $this->t('Height'),
      '#options' => [
        'top' => $this->t('Top'),
        'center' => $this->t('Center'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $this->configuration['anchor']['height'],
      '#description' => $this->t('The anchor that will be used to crop images that are higher than the configured ratio.'),
    ];

    return $form;
  }

}
