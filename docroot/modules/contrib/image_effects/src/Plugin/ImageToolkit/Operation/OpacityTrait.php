<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Opacity operations.
 */
trait OpacityTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'opacity' => [
        'description' => 'Opacity.',
        'required' => FALSE,
        'default' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure opacity is in the range 0-100.
    if (!is_numeric($arguments['opacity']) || $arguments['opacity'] > 100 || $arguments['opacity'] < 0) {
      throw new \InvalidArgumentException("Invalid opacity ('{$arguments['opacity']}') specified for the image 'opacity' operation");
    }
    return $arguments;
  }

}
