<?php

declare(strict_types=1);

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
        'type' => 'int',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Assure brightness level is valid.
    if ($arguments['level'] < -100 || $arguments['level'] > 100) {
      throw new \InvalidArgumentException("Invalid level ('{$arguments['level']}') specified for the image 'brightness' operation");
    }

    return $arguments;
  }

}
