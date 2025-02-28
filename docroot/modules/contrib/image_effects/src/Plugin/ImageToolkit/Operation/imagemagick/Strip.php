<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Strip operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_strip",
 *   toolkit = "imagemagick",
 *   operation = "strip",
 *   label = @Translation("Strip"),
 *   description = @Translation("Strips metadata from an image.")
 * )
 */
class Strip extends ImagemagickImageToolkitOperationBase {

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
    $this->addArgument('-strip');
    return TRUE;
  }

}
