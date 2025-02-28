<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\PixelateTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 Pixelate operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_pixelate",
 *   toolkit = "gd",
 *   operation = "pixelate",
 *   label = @Translation("Pixelate"),
 *   description = @Translation("Pixelates the image.")
 * )
 */
class Pixelate extends GDImageToolkitOperationBase {

  use PixelateTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    return imagefilter($this->getToolkit()->getResource(), IMG_FILTER_PIXELATE, $arguments['size'], TRUE);
  }

}
