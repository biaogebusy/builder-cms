<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MirrorTrait;

/**
 * Defines ImageMagick Mirror operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_mirror",
 *   toolkit = "imagemagick",
 *   operation = "mirror",
 *   label = @Translation("Mirror"),
 *   description = @Translation("Mirror the image horizontally and/or vertically.")
 * )
 */
class Mirror extends ImagemagickImageToolkitOperationBase {

  use MirrorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['x_axis'] === TRUE) {
      $this->addArgument("-flop");
    }
    if ($arguments['y_axis'] === TRUE) {
      $this->addArgument("-flip");
    }
    return TRUE;
  }

}
