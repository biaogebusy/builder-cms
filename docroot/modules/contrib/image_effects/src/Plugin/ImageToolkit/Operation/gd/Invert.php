<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Invert operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_invert",
 *   toolkit = "gd",
 *   operation = "invert",
 *   label = @Translation("Invert"),
 *   description = @Translation("Replace each pixel with its complementary color.")
 * )
 */
class Invert extends GDImageToolkitOperationBase {

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
    return imagefilter($this->getToolkit()->getResource(), IMG_FILTER_NEGATE);
  }

}
