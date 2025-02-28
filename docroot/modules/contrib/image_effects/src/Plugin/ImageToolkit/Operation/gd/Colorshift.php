<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ColorshiftTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Colorshift operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_colorshift",
 *   toolkit = "gd",
 *   operation = "colorshift",
 *   label = @Translation("Colorshift"),
 *   description = @Translation("Shift image colors.")
 * )
 */
class Colorshift extends GDImageToolkitOperationBase {

  use ColorshiftTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $rgb = Color::hexToRgb($arguments['RGB']);
    return imagefilter($this->getToolkit()->getResource(), IMG_FILTER_COLORIZE, $rgb['red'], $rgb['green'], $rgb['blue']);
  }

}
