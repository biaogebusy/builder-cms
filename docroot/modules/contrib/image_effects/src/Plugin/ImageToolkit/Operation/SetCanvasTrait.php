<?php

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
        'required' => FALSE,
        'default' => NULL,
      ],
      'width' => [
        'description' => 'The width of the canvas image, in pixels',
      ],
      'height' => [
        'description' => 'The height of the canvas image, in pixels',
      ],
      'x_pos' => [
        'description' => 'The left offset of the original image on the canvas, in pixels',
      ],
      'y_pos' => [
        'description' => 'The top offset of the original image on the canvas, in pixels',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    return $arguments;
  }

}
