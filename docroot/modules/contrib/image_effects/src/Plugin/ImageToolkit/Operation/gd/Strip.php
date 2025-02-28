<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Strip operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_strip",
 *   toolkit = "gd",
 *   operation = "strip",
 *   label = @Translation("Strip"),
 *   description = @Translation("Strips metadata from an image.")
 * )
 */
class Strip extends GDImageToolkitOperationBase {

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
    // Actually, we have nothing to do here. The GD toolkit drops all EXIF
    // information when loading the image in memory, which is then missing
    // when saving to the derivative image.
    return TRUE;
  }

}
