<?php

declare(strict_types=1);

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
        'type' => 'int',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Assure contrast level is valid.
    if ($arguments['level'] < -100 || $arguments['level'] > 100) {
      throw new \InvalidArgumentException("Invalid level ('{$arguments['level']}') specified for the image 'contrast' operation");
    }

    return $arguments;
  }

}
