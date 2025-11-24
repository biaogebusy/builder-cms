<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for set canvas operations.
 */
trait SetCanvasTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'canvas_color' => [
        'description' => 'Color',
        'type' => '?string',
        'required' => FALSE,
        'default' => NULL,
      ],
      'width' => [
        'description' => 'The width of the canvas image, in pixels',
        'type' => 'int',
      ],
      'height' => [
        'description' => 'The height of the canvas image, in pixels',
        'type' => 'int',
      ],
      'x_pos' => [
        'description' => 'The left offset of the original image on the canvas, in pixels',
        'type' => 'int',
      ],
      'y_pos' => [
        'description' => 'The top offset of the original image on the canvas, in pixels',
        'type' => 'int',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    return ArgumentsTypeValidator::validate($this->arguments(), $arguments);
  }

}
