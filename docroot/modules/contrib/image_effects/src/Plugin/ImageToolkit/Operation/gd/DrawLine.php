<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawLineTrait;

/**
 * Defines GD2 draw line operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_draw_line",
 *   toolkit = "gd",
 *   operation = "draw_line",
 *   label = @Translation("Draw line"),
 *   description = @Translation("Draws on the image a line of the specified color.")
 * )
 */
class DrawLine extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use DrawLineTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($arguments['color']);
    return imageline($this->getToolkit()->getResource(), $arguments['x1'], $arguments['y1'], $arguments['x2'], $arguments['y2'], $color);
  }

}
