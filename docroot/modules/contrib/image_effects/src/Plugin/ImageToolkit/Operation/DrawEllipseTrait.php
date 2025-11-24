<?php

declare(strict_types=1);

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
        'type' => 'int',
      ],
      'cy' => [
        'description' => 'y-coordinate of the center.',
        'type' => 'int',
      ],
      'width' => [
        'description' => 'The ellipse width.',
        'type' => 'int',
      ],
      'height' => [
        'description' => 'The ellipse height.',
        'type' => 'int',
      ],
      'color' => [
        'description' => 'The fill color, in RGBA format.',
        'type' => 'string',
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
