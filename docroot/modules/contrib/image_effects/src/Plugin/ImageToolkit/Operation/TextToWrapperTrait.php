<?php

declare(strict_types=1);

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
        'type' => 'string',
      ],
      'font_size' => [
        'description' => 'Font size.',
        'type' => 'int',
      ],
      'font_angle' => [
        'description' => 'Font rotation angle.',
        'type' => 'float',
      ],
      'font_color' => [
        'description' => 'Font color.',
        'type' => 'string',
      ],
      'font_stroke_mode' => [
        'description' => 'Font stroke mode.',
        'type' => 'string',
      ],
      'font_stroke_color' => [
        'description' => 'Font stroke color.',
        'type' => 'string',
      ],
      'font_outline_top' => [
        'description' => 'Font outline top in pixels.',
        'type' => 'int',
      ],
      'font_outline_right' => [
        'description' => 'Font outline right in pixels.',
        'type' => 'int',
      ],
      'font_outline_bottom' => [
        'description' => 'Font outline bottom in pixels.',
        'type' => 'int',
      ],
      'font_outline_left' => [
        'description' => 'Font outline left in pixels.',
        'type' => 'int',
      ],
      'font_shadow_x_offset' => [
        'description' => 'Font shadow x offset in pixels.',
        'type' => 'int',
      ],
      'font_shadow_y_offset' => [
        'description' => 'Font shadow y offset in pixels.',
        'type' => 'int',
      ],
      'font_shadow_width' => [
        'description' => 'Font shadow width in pixels.',
        'type' => 'int',
      ],
      'font_shadow_height' => [
        'description' => 'Font shadow height in pixels.',
        'type' => 'int',
      ],
      'layout_padding_top' => [
        'description' => 'Layout top padding in pixels.',
        'type' => 'int',
      ],
      'layout_padding_right' => [
        'description' => 'Layout right padding in pixels.',
        'type' => 'int',
      ],
      'layout_padding_bottom' => [
        'description' => 'Layout bottom padding in pixels.',
        'type' => 'int',
      ],
      'layout_padding_left' => [
        'description' => 'Layout left padding in pixels.',
        'type' => 'int',
      ],
      'layout_x_pos' => [
        'description' => 'Layout horizontal position.',
        'type' => 'string',
      ],
      'layout_y_pos' => [
        'description' => 'Layout vertical position.',
        'type' => 'string',
      ],
      'layout_x_offset' => [
        'description' => 'Layout horizontal offset.',
        'type' => 'int',
      ],
      'layout_y_offset' => [
        'description' => 'Layout vertical offset.',
        'type' => 'int',
      ],
      'layout_background_color' => [
        'description' => 'Layout background color.',
        'type' => 'string',
      ],
      'layout_overflow_action' => [
        'description' => 'Layout overflow action.',
        'type' => 'string',
      ],
      'text_maximum_width' => [
        'description' => 'Maximum width, in pixels.',
        'type' => 'int',
      ],
      'text_fixed_width' => [
        'description' => 'Specifies if the width is fixed.',
        'type' => 'bool',
      ],
      'text_align' => [
        'description' => 'Alignment of the text lines (left/right/center).',
        'type' => 'string',
      ],
      'text_line_spacing' => [
        'description' => 'Space between text lines (leading), pixels.',
        'type' => 'int',
      ],
      'text_string' => [
        'description' => 'Actual text string to be placed on the image.',
        'type' => 'string',
      ],
      'canvas_width' => [
        'description' => 'Width of the underlying image.',
        'type' => 'int',
      ],
      'canvas_height' => [
        'description' => 'Height of the underlying image.',
        'type' => 'int',
      ],
      'debug_visuals' => [
        'description' => 'Indicates if text bounding boxes need to be visualised. Only used in debugging.',
        'type' => 'bool',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    if (empty($arguments['font_uri'])) {
      throw new \InvalidArgumentException("No font file URI passed to the 'text_to_wrapper' operation");
    }

    return $arguments;
  }

}
