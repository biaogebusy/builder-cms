<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects TextOverlay operations.
 */
trait TextOverlayTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'font_uri' => [
        'description' => 'Font file URI.',
      ],
      'font_size' => [
        'description' => 'Font size.',
      ],
      'font_angle' => [
        'description' => 'Font rotation angle.',
      ],
      'font_color' => [
        'description' => 'Font color.',
      ],
      'font_stroke_mode' => [
        'description' => 'Font stroke mode.',
      ],
      'font_stroke_color' => [
        'description' => 'Font stroke color.',
      ],
      'font_outline_top' => [
        'description' => 'Font outline top in pixels.',
      ],
      'font_outline_right' => [
        'description' => 'Font outline right in pixels.',
      ],
      'font_outline_bottom' => [
        'description' => 'Font outline bottom in pixels.',
      ],
      'font_outline_left' => [
        'description' => 'Font outline left in pixels.',
      ],
      'font_shadow_x_offset' => [
        'description' => 'Font shadow x offset in pixels.',
      ],
      'font_shadow_y_offset' => [
        'description' => 'Font shadow y offset in pixels.',
      ],
      'font_shadow_width' => [
        'description' => 'Font shadow width in pixels.',
      ],
      'font_shadow_height' => [
        'description' => 'Font shadow height in pixels.',
      ],
      'text' => [
        'description' => 'The text string in UTF-8 encoding.',
      ],
      'basepoint' => [
        'description' => 'The basepoint of the text to be overlaid.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    if (empty($arguments['font_uri'])) {
      throw new \InvalidArgumentException("No font file URI passed to the 'text_overlay' operation");
    }
    return $arguments;
  }

}
