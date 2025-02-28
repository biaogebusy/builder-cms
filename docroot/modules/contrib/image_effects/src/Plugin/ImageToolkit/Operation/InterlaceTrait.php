<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Interlace operations.
 */
trait InterlaceTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'type' => [
        'description' => 'The interlace type.',
        'required' => FALSE,
        'default' => 'Plane',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure interlace type is valid.
    if (!$arguments['type'] || !in_array($arguments['type'], ['Line', 'Plane'])) {
      throw new \InvalidArgumentException("Invalid type '{$arguments['type']}' specified for the image 'interlace' operation");
    }

    return $arguments;
  }

}
