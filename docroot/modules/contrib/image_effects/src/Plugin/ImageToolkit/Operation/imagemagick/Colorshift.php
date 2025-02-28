<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ColorshiftTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Colorshift operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_colorshift",
 *   toolkit = "imagemagick",
 *   operation = "colorshift",
 *   label = @Translation("Colorshift"),
 *   description = @Translation("Shift image colors.")
 * )
 */
class Colorshift extends ImagemagickImageToolkitOperationBase {

  use ColorshiftTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArgument("+level-colors " . $this->escapeArgument($arguments['RGB']) . ",white");
    return TRUE;
  }

}
