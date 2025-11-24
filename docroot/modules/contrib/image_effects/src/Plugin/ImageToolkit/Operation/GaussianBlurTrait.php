<?php

declare(strict_types=1);

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
        'type' => 'int',
      ],
      'sigma' => [
        'description' => 'The blur sigma value.',
        'type' => 'float',
        'required' => FALSE,
        'default' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Assure blur radius is valid.
    if ($arguments['radius'] < 1) {
      throw new \InvalidArgumentException("Invalid radius ('{$arguments['radius']}') specified for the image 'gaussian_blur' operation");
    }
    // Assure sigma value is valid.
    if ($arguments['sigma'] !== NULL && $arguments['sigma'] <= 0) {
      throw new \InvalidArgumentException("Invalid sigma value ('{$arguments['sigma']}') specified for the image 'gaussian_blur' operation");
    }

    return $arguments;
  }

}
