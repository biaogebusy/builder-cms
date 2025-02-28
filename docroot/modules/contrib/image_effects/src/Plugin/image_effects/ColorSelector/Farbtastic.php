<?php

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * Farbtastic color selector plugin.
 *
 * @Plugin(
 *   id = "farbtastic",
 *   title = @Translation("Farbtastic color selector"),
 *   short_title = @Translation("Farbtastic"),
 *   help = @Translation("Use a Farbtastic color picker to select colors.")
 * )
 */
class Farbtastic extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    // @todo remove versioning once Drupal 9 is no longer  supported.
    $libraryVersion = (version_compare(\Drupal::VERSION, 10) >= 0) ? '_v2' : '';
    return [
      '#type' => 'textfield',
      '#title' => isset($options['#title']) ? $options['#title'] : $this->t('Color'),
      '#description' => isset($options['#description']) ? $options['#description'] : NULL,
      '#default_value' => $options['#default_value'],
      '#field_suffix' => '<div class="farbtastic-colorpicker"></div>',
      '#maxlength' => 7,
      '#size' => 8,
      '#wrapper_attributes' => ['class' => ['image-effects-farbtastic-color-selector']],
      '#attributes' => ['class' => ['image-effects-color-textfield']],
      '#attached' => ['library' => ['image_effects/image_effects.farbtastic_color_selector' . $libraryVersion]],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    // @todo remove versioning once Drupal 9 is no longer  supported.
    if (version_compare(\Drupal::VERSION, 10) >= 0) {
      return \Drupal::service('module_handler')->moduleExists('color');
    }
    return TRUE;
  }

}
