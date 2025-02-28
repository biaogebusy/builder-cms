<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetGifTransparentColorTrait;

/**
 * Defines GD2 set_gif_transparent_color image operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_set_gif_transparent_color",
 *   toolkit = "gd",
 *   operation = "set_gif_transparent_color",
 *   label = @Translation("Set the image transparent color"),
 *   description = @Translation("Set the image transparent color for GIF images.")
 * )
 */
class SetGifTransparentColor extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use SetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getType() == IMAGETYPE_GIF && $arguments['transparent_color']) {
      $rgb = Color::hexToRgb($arguments['transparent_color']);
      $color = imagecolorallocate($this->getToolkit()->getResource(), $rgb['red'], $rgb['green'], $rgb['blue']);
      imagecolortransparent($this->getToolkit()->getResource(), $color);
    }
    return TRUE;
  }

}
