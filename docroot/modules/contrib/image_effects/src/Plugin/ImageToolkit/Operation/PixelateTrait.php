<?php

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
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure pixelate size is valid.
    if (!is_numeric($arguments['size']) || (int) $arguments['size'] != $arguments['size'] || $arguments['size'] < 1) {
      throw new \InvalidArgumentException("Invalid size ('{$arguments['size']}') specified for the image 'pixelate' operation");
    }
    return $arguments;
  }

}
