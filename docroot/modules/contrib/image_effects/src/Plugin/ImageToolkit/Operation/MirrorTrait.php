<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

/**
 * Base trait for image_effects Mirror operations.
 */
trait MirrorTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'x_axis' => [
        'description' => 'Flop the source image horizontally.',
        'required' => FALSE,
        'default' => FALSE,
      ],
      'y_axis' => [
        'description' => 'Flip the source image vertically.',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure either horizontal flop or vertical flip is requested.
    $arguments['x_axis'] = (bool) $arguments['x_axis'];
    $arguments['y_axis'] = (bool) $arguments['y_axis'];
    if ($arguments['x_axis'] === FALSE && $arguments['y_axis'] === FALSE) {
      throw new \InvalidArgumentException("Neither horizontal flop nor vertical flip is specified for the image 'mirror' operation");
    }
    return $arguments;
  }

}
