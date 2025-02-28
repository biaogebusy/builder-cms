<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\Color;
use Drupal\image_effects\Component\ColorUtility;

/**
 * Base trait for image_effects Rotate operations.
 */
trait RotateTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'degrees' => [
        'description' => 'The number of (clockwise) degrees to rotate the image',
      ],
      'background' => [
        'description' => "A string specifying the hexadecimal color code to use as background for the uncovered area of the image after the rotation, in RGBA format",
        'required' => FALSE,
        'default' => NULL,
      ],
      'fallback_transparency_color' => [
        'description' => "A string specifying the hexadecimal color code to use as fallback for transparent background, in RGB format",
        'required' => FALSE,
        'default' => '#FFFFFF',
      ],
      'resize_filter' => [
        'description' => 'An optional filter to apply for the resize',
        'required' => FALSE,
        'default' => '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    if (!empty($arguments['background']) && !ColorUtility::validateRgba($arguments['background'])) {
      throw new \InvalidArgumentException("Invalid background color '{$arguments['background']}' specified for the 'rotate' operation.");
    }
    if (!empty($arguments['fallback_transparency_color']) && !Color::validateHex($arguments['fallback_transparency_color'])) {
      throw new \InvalidArgumentException("Invalid fallback color '{$arguments['fallback_transparency_color']}' specified for the 'rotate' operation.");
    }
    return $arguments;
  }

}
