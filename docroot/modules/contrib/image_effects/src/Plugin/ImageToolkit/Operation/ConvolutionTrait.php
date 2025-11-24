<?php

declare(strict_types=1);

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
        'type' => 'array',
      ],
      'divisor' => [
        'description' => 'Typically the matrix entries sum (normalization).',
        'type' => 'float',
      ],
      'offset' => [
        'description' => 'This value is added to the division result.',
        'type' => 'float',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure convolution parameters are valid.
    $kernel = [];
    foreach ($arguments['kernel'] as $i => $row) {
      foreach ($row as $kernel_entry) {
        if (!is_numeric($kernel_entry)) {
          throw new \InvalidArgumentException("Invalid kernel entry ('{$kernel_entry}') specified for the image 'convolution' operation");
        }
        $kernel[$i][] = (float) $kernel_entry;
      }
    }
    $arguments['kernel'] = $kernel;

    return $arguments;
  }

}
