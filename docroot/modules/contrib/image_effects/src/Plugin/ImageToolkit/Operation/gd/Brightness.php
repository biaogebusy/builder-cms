<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\BrightnessTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Brightness operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_brightness",
 *   toolkit = "gd",
 *   operation = "brightness",
 *   label = @Translation("Brightness"),
 *   description = @Translation("Adjust image brightness.")
 * )
 */
class Brightness extends GDImageToolkitOperationBase {

  use BrightnessTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      return imagefilter($this->getToolkit()->getResource(), IMG_FILTER_BRIGHTNESS, round($arguments['level'] / 100 * 255));
    }

    return TRUE;
  }

}
