<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects DrawEllipse operations.
 */
trait DrawEllipseTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'cx' => [
        'description' => 'x-coordinate of the center.',
      ],
      'cy' => [
        'description' => 'y-coordinate of the center.',
      ],
      'width' => [
        'description' => 'The ellipse width.',
      ],
      'height' => [
        'description' => 'The ellipse height.',
      ],
      'color' => [
        'description' => 'The fill color, in RGBA format.',
      ],
    ];
  }

}
