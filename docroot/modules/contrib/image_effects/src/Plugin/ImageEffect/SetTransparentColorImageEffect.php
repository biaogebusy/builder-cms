<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Set the image transparent color for GIF images.
 */
#[ImageEffect(
  id: 'image_effects_set_transparent_color',
  label: new TranslatableMarkup('Set transparent color'),
  description: new TranslatableMarkup('Set the image transparent color for GIF images.'),
)]
class SetTransparentColorImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'transparent_color' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    if ($data['transparent_color']) {
      $data['color_info'] = [
        '#theme' => 'image_effects_color_detail',
        '#color' => $data['transparent_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      ];
    }

    return [
      '#theme' => 'image_effects_set_transparent_color_summary',
      '#data' => $data,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['transparent_color'] = [
      '#type' => 'image_effects_color',
      '#description'  => $this->t('Select a color to be used for transparency of GIF image files. Leave the checkbox ticked to use the color of the image being processed, if it has one.'),
      '#allow_null' => TRUE,
      '#checkbox_title' => $this->t('Use source image transparent color'),
      '#allow_opacity' => FALSE,
      '#default_value' => $this->configuration['transparent_color'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['transparent_color'] = $form_state->getValue('transparent_color');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('set_gif_transparent_color', ['transparent_color' => $this->configuration['transparent_color']]);
  }

}
