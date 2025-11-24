<?php

declare(strict_types=1);

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
        'type' => 'int',
      ],
      'y1' => [
        'description' => 'y-coordinate for first point.',
        'type' => 'int',
      ],
      'x2' => [
        'description' => 'x-coordinate for second point.',
        'type' => 'int',
      ],
      'y2' => [
        'description' => 'y-coordinate for second point.',
        'type' => 'int',
      ],
      'color' => [
        'description' => 'The line color, in RGBA format.',
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
