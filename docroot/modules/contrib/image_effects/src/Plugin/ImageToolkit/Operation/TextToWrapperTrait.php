<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Text Overlay text-to-wrapper operations.
 */
trait TextToWrapperTrait {

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
      'layout_padding_top' => [
        'description' => 'Layout top padding in pixels.',
      ],
      'layout_padding_right' => [
        'description' => 'Layout right padding in pixels.',
      ],
      'layout_padding_bottom' => [
        'description' => 'Layout bottom padding in pixels.',
      ],
      'layout_padding_left' => [
        'description' => 'Layout left padding in pixels.',
      ],
      'layout_x_pos' => [
        'description' => 'Layout horizontal position.',
      ],
      'layout_y_pos' => [
        'description' => 'Layout vertical position.',
      ],
      'layout_x_offset' => [
        'description' => 'Layout horizontal offset.',
      ],
      'layout_y_offset' => [
        'description' => 'Layout vertical offset.',
      ],
      'layout_background_color' => [
        'description' => 'Layout background color.',
      ],
      'layout_overflow_action' => [
        'description' => 'Layout overflow action.',
      ],
      'text_maximum_width' => [
        'description' => 'Maximum width, in pixels.',
      ],
      'text_fixed_width' => [
        'description' => 'Specifies if the width is fixed.',
      ],
      'text_align' => [
        'description' => 'Alignment of the text lines (left/right/center).',
      ],
      'text_line_spacing' => [
        'description' => 'Space between text lines (leading), pixels.',
      ],
      'text_string' => [
        'description' => 'Actual text string to be placed on the image.',
      ],
      'canvas_width' => [
        'description' => 'Width of the underlying image.',
      ],
      'canvas_height' => [
        'description' => 'Height of the underlying image.',
      ],
      'debug_visuals' => [
        'description' => 'Indicates if text bounding boxes need to be visualised. Only used in debugging.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    if (empty($arguments['font_uri'])) {
      throw new \InvalidArgumentException("No font file URI passed to the 'text_to_wrapper' operation");
    }
    return $arguments;
  }

}
