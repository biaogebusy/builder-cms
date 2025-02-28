<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Brightness operations.
 */
trait BrightnessTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'level' => [
        'description' => 'The brightness level.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure brightness level is valid.
    if (!is_numeric($arguments['level']) || !is_int($arguments['level'] + 5) || $arguments['level'] < -100 || $arguments['level'] > 100) {
      throw new \InvalidArgumentException("Invalid level ('{$arguments['level']}') specified for the image 'brightness' operation");
    }
    return $arguments;
  }

}
