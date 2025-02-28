<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ContrastTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Contrast operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_contrast",
 *   toolkit = "gd",
 *   operation = "contrast",
 *   label = @Translation("Contrast"),
 *   description = @Translation("Adjust image contrast.")
 * )
 */
class Contrast extends GDImageToolkitOperationBase {

  use ContrastTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      return imagefilter($this->getToolkit()->getResource(), IMG_FILTER_CONTRAST, $arguments['level'] * -1);
    }

    return TRUE;
  }

}
