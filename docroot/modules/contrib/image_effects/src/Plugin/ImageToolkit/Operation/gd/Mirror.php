<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MirrorTrait;

/**
 * Defines GD Mirror operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_mirror",
 *   toolkit = "gd",
 *   operation = "mirror",
 *   label = @Translation("Mirror"),
 *   description = @Translation("Mirror the image horizontally and/or vertically.")
 * )
 */
class Mirror extends GDImageToolkitOperationBase {

  use MirrorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['x_axis'] === TRUE && $arguments['y_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getResource(), IMG_FLIP_BOTH);
    }
    elseif ($arguments['x_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getResource(), IMG_FLIP_HORIZONTAL);
    }
    elseif ($arguments['y_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getResource(), IMG_FLIP_VERTICAL);
    }
  }

}
