<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\OpacityTrait;

/**
 * Defines ImageMagick Opacity operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_opacity",
 *   toolkit = "imagemagick",
 *   operation = "opacity",
 *   label = @Translation("Opacity"),
 *   description = @Translation("Adjust image transparency.")
 * )
 */
class Opacity extends ImagemagickImageToolkitOperationBase {

  use OpacityTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getExecManager()->getPackage() === 'graphicsmagick') {
      // GraphicsMagick does not support -alpha argument, return early.
      // @todo implement a GraphicsMagick solution if possible.
      return FALSE;
    }

    switch ($arguments['opacity']) {
      case 100:
        // Fully opaque, leave image as-is.
        break;

      case 0:
        // Fully transparent, set full transparent for all pixels.
        $this->addArgument("-alpha set -channel Alpha -evaluate Set 0%");
        break;

      default:
        // Divide existing alpha to the opacity needed. This preserves
        // partially transparent images.
        $divide = number_format((float) (100 / $arguments['opacity']), 4, '.', ',');
        $this->addArgument("-alpha set -channel Alpha -evaluate Divide {$divide}");
        break;

    }

    return TRUE;
  }

}
