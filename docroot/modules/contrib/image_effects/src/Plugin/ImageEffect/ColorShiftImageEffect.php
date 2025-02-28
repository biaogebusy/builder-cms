<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Shift image colors.
 *
 * Originally contributed to the imagecache_actions module by
 * dan http://coders.co.nz.
 * sydneyshan http://enigmadigital.net.au.
 *
 * @ImageEffect(
 *   id = "image_effects_color_shift",
 *   label = @Translation("Color Shift"),
 *   description = @Translation("Shift image colors.")
 * )
 */
class ColorShiftImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'RGB' => '#FF0000',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    if ($data['RGB']) {
      $data['color_info'] = [
        '#theme' => 'image_effects_color_detail',
        '#color' => $data['RGB'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      ];
    }

    return [
      '#theme' => 'image_effects_color_shift_summary',
      '#data' => $data,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['RGB'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Color shift'),
      '#description'  => $this->t("Note that colorshift is a mathematical filter that doesn't always have the expected result. To shift an image precisely TO a target color, desaturate (greyscale) it before colorizing. The hue (color wheel) is the <em>direction</em> the existing colors are shifted. The tone (inner box) is the amount. Keep the tone half-way up the left site of the color box for best results."),
      '#default_value' => $this->configuration['RGB'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['RGB'] = $form_state->getValue('RGB');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('colorshift', ['RGB' => $this->configuration['RGB']]);
  }

}
