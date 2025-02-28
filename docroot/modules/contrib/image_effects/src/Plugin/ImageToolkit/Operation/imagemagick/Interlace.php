<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\InterlaceTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Interlace operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_interlace",
 *   toolkit = "imagemagick",
 *   operation = "interlace",
 *   label = @Translation("Interlace"),
 *   description = @Translation("Create an interlaced PNG or GIF or progressive JPEG image.")
 * )
 */
class Interlace extends ImagemagickImageToolkitOperationBase {

  use InterlaceTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArgument("-interlace {$arguments['type']}");
    return TRUE;
  }

}
