<?php

namespace Drupal\image_effects\Plugin\image_effects\ColorSelector;

use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * HTML color selector plugin.
 *
 * @Plugin(
 *   id = "html_color",
 *   title = @Translation("HTML color selector"),
 *   short_title = @Translation("HTML color"),
 *   help = @Translation("Use an HTML5 color element to select colors.")
 * )
 */
class HtmlColor extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    return [
      '#type' => 'color',
      '#title'   => isset($options['#title']) ? $options['#title'] : $this->t('Color'),
      '#description' => isset($options['#description']) ? $options['#description'] : NULL,
      '#default_value' => $options['#default_value'],
      '#field_suffix' => $options['#default_value'],
      '#wrapper_attributes' => ['class' => ['image-effects-html-color-selector']],
      '#maxlength' => 7,
      '#size' => 7,
      '#attached' => ['library' => ['image_effects/image_effects.html_color_selector']],
    ];
  }

}
