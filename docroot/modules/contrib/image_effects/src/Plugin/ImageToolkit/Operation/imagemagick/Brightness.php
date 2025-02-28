<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\BrightnessTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Brightness operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_brightness",
 *   toolkit = "imagemagick",
 *   operation = "brightness",
 *   label = @Translation("Brightness"),
 *   description = @Translation("Adjust image brightness.")
 * )
 */
class Brightness extends ImagemagickImageToolkitOperationBase {

  use BrightnessTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      $this->addArgument('-brightness-contrast ' . $this->escapeArgument($arguments['level']));
    }

    return TRUE;
  }

}
