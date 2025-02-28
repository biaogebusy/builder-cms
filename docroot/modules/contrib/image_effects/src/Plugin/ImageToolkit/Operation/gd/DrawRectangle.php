<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawRectangleTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 draw rectangle operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_draw_rectangle",
 *   toolkit = "gd",
 *   operation = "draw_rectangle",
 *   label = @Translation("Draw rectangle"),
 *   description = @Translation("Draws  a rectangle on the image, optionally filling it in with a specified color.")
 * )
 */
class DrawRectangle extends GDImageToolkitOperationBase {

  use DrawRectangleTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $success = TRUE;
    if ($arguments['fill_color']) {
      $color = $this->allocateColorFromRgba($arguments['fill_color']);
      // @todo cleanup once once Drupal 9 is no longer  supported.
      if (PHP_VERSION_ID >= 80000) {
        $success = imagefilledpolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), $color);
      }
      else {
        $success = imagefilledpolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
      }
    }
    if ($success && $arguments['border_color']) {
      $color = $this->allocateColorFromRgba($arguments['border_color']);
      // @todo cleanup once once Drupal 9 is no longer  supported.
      if (PHP_VERSION_ID >= 80000) {
        $success = imagepolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), $color);
      }
      else {
        $success = imagepolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
      }
    }
    return $success;
  }

}
