<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Blur operations.
 */
trait GaussianBlurTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'radius' => [
        'description' => 'The blur radius, in pixels.',
      ],
      'sigma' => [
        'description' => 'The blur sigma value.',
        'required' => FALSE,
        'default' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure blur radius is valid.
    if (!is_int($arguments['radius']) || $arguments['radius'] < 1) {
      throw new \InvalidArgumentException("Invalid radius ('{$arguments['radius']}') specified for the image 'gaussian_blur' operation");
    }
    // Assure sigma value is valid.
    if ($arguments['sigma'] !== NULL && (!is_float($arguments['sigma']) || $arguments['sigma'] <= 0)) {
      throw new \InvalidArgumentException("Invalid sigma value ('{$arguments['sigma']}') specified for the image 'gaussian_blur' operation");
    }
    return $arguments;
  }

}
