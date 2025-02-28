<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Contrast operations.
 */
trait ContrastTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'level' => [
        'description' => 'The contrast level.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure contrast level is valid.
    if (!is_numeric($arguments['level']) || !is_int($arguments['level'] + 5) || $arguments['level'] < -100 || $arguments['level'] > 100) {
      throw new \InvalidArgumentException("Invalid level ('{$arguments['level']}') specified for the image 'contrast' operation");
    }
    return $arguments;
  }

}
