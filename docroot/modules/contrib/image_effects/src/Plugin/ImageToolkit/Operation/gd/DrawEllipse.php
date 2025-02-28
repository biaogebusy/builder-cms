<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawEllipseTrait;

/**
 * Defines GD2 draw ellipse operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_draw_ellipse",
 *   toolkit = "gd",
 *   operation = "draw_ellipse",
 *   label = @Translation("Draw ellipse"),
 *   description = @Translation("Draws on the image an ellipse of the specified color.")
 * )
 */
class DrawEllipse extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use DrawEllipseTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($arguments['color']);
    return imagefilledellipse($this->getToolkit()->getResource(), $arguments['cx'], $arguments['cy'], $arguments['width'], $arguments['height'], $color);
  }

}
