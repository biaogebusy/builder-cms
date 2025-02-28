<?php

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * JQuery Colorpicker color selector plugin.
 *
 * @Plugin(
 *   id = "jquery_colorpicker",
 *   title = @Translation("JQuery Colorpicker color selector"),
 *   short_title = @Translation("JQuery Colorpicker"),
 *   help = @Translation("Use a JQuery color picker to select colors.")
 * )
 */
class JqueryColorPicker extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    return [
      '#type' => 'jquery_colorpicker',
      '#title' => isset($options['#title']) ? $options['#title'] : $this->t('Color'),
      '#default_value' => $options['#default_value'],
      '#attributes' => ['class' => ['image-effects-jquery-colorpicker']],
      '#wrapper_attributes' => ['class' => ['image-effects-jquery-colorpicker-color-selector']],
      '#attached' => ['library' => ['image_effects/image_effects.jquery_colorpicker_color_selector']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return \Drupal::service('module_handler')->moduleExists('jquery_colorpicker');
  }

}
