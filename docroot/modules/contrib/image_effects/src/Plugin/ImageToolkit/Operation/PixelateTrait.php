<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Pixelate operations.
 */
trait PixelateTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'size' => [
        'description' => 'The size of the pixels.',
        'type' => 'int',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Assure pixelate size is valid.
    if ($arguments['size'] < 1) {
      throw new \InvalidArgumentException("Invalid size ('{$arguments['size']}') specified for the image 'pixelate' operation");
    }

    return $arguments;
  }

}
