<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects DrawLine operations.
 */
trait DrawLineTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'x1' => [
        'description' => 'x-coordinate for first point.',
      ],
      'y1' => [
        'description' => 'y-coordinate for first point.',
      ],
      'x2' => [
        'description' => 'x-coordinate for second point.',
      ],
      'y2' => [
        'description' => 'y-coordinate for second point.',
      ],
      'color' => [
        'description' => 'The line color, in RGBA format.',
      ],
    ];
  }

}
