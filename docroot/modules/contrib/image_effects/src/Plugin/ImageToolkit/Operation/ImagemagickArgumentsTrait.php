<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for ImageMagick arguments operations.
 */
trait ImagemagickArgumentsTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'command_line' => [
        'description' => 'Command line arguments.',
        'type' => 'string',
      ],
      'width' => [
        'description' => 'Width of image after operation.',
        'type' => 'int',
      ],
      'height' => [
        'description' => 'Height of image after operation.',
        'type' => 'int',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure 'width' is NULL or a positive integer.
    if ($arguments['width'] !== NULL && $arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image 'imagemagick_arguments' operation");
    }
    // Ensure 'height' is NULL or a positive integer.
    if ($arguments['height'] !== NULL && $arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image 'imagemagick_arguments' operation");
    }

    return $arguments;
  }

}
