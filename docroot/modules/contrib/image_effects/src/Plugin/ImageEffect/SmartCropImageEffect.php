<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;

/**
 * Crop an image preserving the portion with the most entropy.
 *
 * @ImageEffect(
 *   id = "image_effects_smart_crop",
 *   label = @Translation("Smart Crop"),
 *   description = @Translation("Similar to Crop, but preserves the portion of the image with the most entropy.")
 * )
 */
class SmartCropImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'width' => NULL,
      'height' => NULL,
      'square' => FALSE,
      'simulate' => FALSE,
      'algorithm' => 'entropy_slice',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_smart_crop_summary',
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
    $form['square'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['square'],
      '#title' => $this->t('Square'),
      '#description' => $this->t('Forces the crop to be a square. Applies if only one crop dimension is set, and specified as % of the source image.'),
      '#states' => [
        'visible' => [
          [':radio[name="data[width][c0][c1][uom]"]' => ['value' => 'perc']],
          [':radio[name="data[height][c0][c1][uom]"]' => ['value' => 'perc']],
        ],
      ],
    ];

    $form['advanced'] = [
      '#type'  => 'details',
      '#title' => $this->t('Advanced settings'),
    ];
    $form['advanced']['algorithm'] = [
      '#type'  => 'select',
      '#title' => $this->t('Calculation algorithm'),
      '#options' => [
        'entropy_slice' => $this->t('Image entropy - slicing'),
        'entropy_grid' => $this->t('Image entropy - recursive grid'),
      ],
      '#description' => $this->t('Select an algorithm to use to determine the crop area.'),
      '#default_value' => $this->configuration['algorithm'],
    ];
    $form['advanced']['simulate'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['simulate'],
      '#title' => $this->t('Simulate'),
      '#description' => $this->t('If selected, the crop will not be executed; the crop area will be highlighted on the source image instead.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $width = $form_state->getValue('width');
    $height = $form_state->getValue('height');
    if (((bool) $width) === FALSE && ((bool) $height) === FALSE) {
      $form_state->setError($form, $this->t("Either <em>Width</em> or <em>Height</em> must be specified."));
    }
    if (strpos($width, '%') !== FALSE && ((int) str_replace('%', '', $width)) > 100) {
      $form_state->setErrorByName('width', $this->t("A percentage crop can not be wider than the source image."));
    }
    if (strpos($height, '%') !== FALSE && ((int) str_replace('%', '', $height)) > 100) {
      $form_state->setErrorByName('height', $this->t("A percentage crop can not be higher than the source image."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $width = $form_state->getValue('width');
    $height = $form_state->getValue('height');
    $this->configuration['width'] = $width;
    $this->configuration['height'] = $height;
    if (strpos($width, '%') !== FALSE || strpos($height, '%') !== FALSE) {
      $this->configuration['square'] = $form_state->getValue('square');
    }
    else {
      $this->configuration['square'] = FALSE;
    }
    $this->configuration['algorithm'] = $form_state->getValue(['advanced', 'algorithm']);
    $this->configuration['simulate'] = $form_state->getValue(['advanced', 'simulate']);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    if (!$dimensions['width'] || !$dimensions['height']) {
      return;
    }
    if ($this->configuration['simulate']) {
      return;
    }
    $d = ImageUtility::resizeDimensions($dimensions['width'], $dimensions['height'], $this->configuration['width'], $this->configuration['height'], $this->configuration['square']);
    // Ensure crop dimensions fit in the source image.
    $dimensions['width'] = min($dimensions['width'], $d['width']);
    $dimensions['height'] = min($dimensions['height'], $d['height']);
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $dimensions = ImageUtility::resizeDimensions($image->getWidth(), $image->getHeight(), $this->configuration['width'], $this->configuration['height'], $this->configuration['square']);
    return $image->apply('smart_crop', [
      'width' => min($dimensions['width'], $image->getWidth()),
      'height' => min($dimensions['height'], $image->getHeight()),
      'algorithm' => $this->configuration['algorithm'],
      'simulate' => $this->configuration['simulate'],
    ]);
  }

}
