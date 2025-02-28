<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Convolution operations.
 */
trait ConvolutionTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'kernel' => [
        'description' => 'The convolution kernel matrix.',
      ],
      'divisor' => [
        'description' => 'Typically the matrix entries sum (normalization).',
      ],
      'offset' => [
        'description' => 'This value is added to the division result.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure convolution parameters are valid.
    foreach ($arguments['kernel'] as $row) {
      foreach ($row as $kernel_entry) {
        if (!is_numeric($kernel_entry)) {
          throw new \InvalidArgumentException("Invalid kernel entry ('{$arguments['divisor']}') specified for the image 'convolution' operation");
        }
      }
    }
    if (!is_numeric($arguments['divisor'])) {
      throw new \InvalidArgumentException("Invalid divisor ('{$arguments['divisor']}') specified for the image 'convolution' operation");
    }
    if (!is_numeric($arguments['offset'])) {
      throw new \InvalidArgumentException("Invalid offset ('{$arguments['offset']}') specified for the image 'convolution' operation");
    }
    return $arguments;
  }

}
