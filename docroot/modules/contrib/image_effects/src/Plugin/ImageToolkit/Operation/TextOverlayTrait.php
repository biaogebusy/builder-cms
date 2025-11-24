<?php

declare(strict_types=1);

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
      'text' => [
        'description' => 'The text string in UTF-8 encoding.',
        'type' => 'string',
      ],
      'basepoint' => [
        'description' => 'The basepoint of the text to be overlaid.',
        'type' => 'array',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    if (empty($arguments['font_uri'])) {
      throw new \InvalidArgumentException("No font file URI passed to the 'text_overlay' operation");
    }

    return $arguments;
  }

}
