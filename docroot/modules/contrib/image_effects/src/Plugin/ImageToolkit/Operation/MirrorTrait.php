<?php

declare(strict_types=1);

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
        'type' => 'bool',
        'required' => FALSE,
        'default' => FALSE,
      ],
      'y_axis' => [
        'description' => 'Flip the source image vertically.',
        'type' => 'bool',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure either horizontal flop or vertical flip is requested.
    if ($arguments['x_axis'] === FALSE && $arguments['y_axis'] === FALSE) {
      throw new \InvalidArgumentException("Neither horizontal flop nor vertical flip is specified for the image 'mirror' operation");
    }

    return $arguments;
  }

}
