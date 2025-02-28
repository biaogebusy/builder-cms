<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Invert operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_invert",
 *   toolkit = "imagemagick",
 *   operation = "invert",
 *   label = @Translation("Invert"),
 *   description = @Translation("Replace each pixel with its complementary color.")
 * )
 */
class Invert extends ImagemagickImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    // This operation does not use any parameters.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArgument('-negate');
    return TRUE;
  }

}
