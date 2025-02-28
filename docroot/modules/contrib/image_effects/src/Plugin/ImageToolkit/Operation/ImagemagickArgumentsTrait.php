<?php

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
      ],
      'width' => [
        'description' => 'Width of image after operation.',
      ],
      'height' => [
        'description' => 'Height of image after operation.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure 'width' is NULL or a positive integer.
    $arguments['width'] = $arguments['width'] !== NULL ? (int) $arguments['width'] : NULL;
    if ($arguments['width'] !== NULL && $arguments['width'] <= 0) {
      throw new \InvalidArgumentException("Invalid width ('{$arguments['width']}') specified for the image 'imagemagick_arguments' operation");
    }
    // Ensure 'height' is NULL or a positive integer.
    $arguments['height'] = $arguments['height'] !== NULL ? (int) $arguments['height'] : NULL;
    if ($arguments['height'] !== NULL && $arguments['height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['height']}') specified for the image 'imagemagick_arguments' operation");
    }
    return $arguments;
  }

}
