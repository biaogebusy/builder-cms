<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\Color;

/**
 * Base trait for set_gif_transparent_color image operations.
 */
trait SetGifTransparentColorTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'transparent_color' => [
        'description' => 'The RGB hex color for GIF transparency',
        'required' => FALSE,
        'default' => '#ffffff',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure transparent color is a valid hex string.
    if ($arguments['transparent_color'] && !Color::validateHex($arguments['transparent_color'])) {
      $transparent_color = $arguments['transparent_color'];
      throw new \InvalidArgumentException("Invalid transparent color ({$transparent_color}) specified for the image 'set_gif_transparent_color' operation");
    }

    return $arguments;
  }

}
