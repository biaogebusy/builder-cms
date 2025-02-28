<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\SmartCropTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 Smart Crop operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_smart_crop",
 *   toolkit = "gd",
 *   operation = "smart_crop",
 *   label = @Translation("Smart Crop"),
 *   description = @Translation("Similar to Crop, but preserves the portion of the image with the most entropy.")
 * )
 */
class SmartCrop extends GDImageToolkitOperationBase {

  use SmartCropTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {

    switch ($arguments['algorithm']) {
      case 'entropy_slice':
        $rect = $this->getEntropyCropBySlicing($this->getToolkit()->getResource(), $arguments['width'], $arguments['height']);
        break;

      case 'entropy_grid':
        $rect = $this->getEntropyCropByGridding($this->getToolkit()->getResource(), $arguments['width'], $arguments['height'], $arguments['simulate'], $arguments['algorithm_params']['grid_width'], $arguments['algorithm_params']['grid_height'], $arguments['algorithm_params']['grid_rows'], $arguments['algorithm_params']['grid_cols'], $arguments['algorithm_params']['grid_sub_rows'], $arguments['algorithm_params']['grid_sub_cols']);
        break;

    }
    $points = $this->getRectangleCorners($rect);

    // Crop the image using the coordinates found above. If simulating, draw
    // a marker on the image instead.
    if (!$arguments['simulate']) {
      return $this->getToolkit()->apply('crop', [
        'x' => $points[6],
        'y' => $points[7],
        'width' => $rect->getWidth(),
        'height' => $rect->getHeight(),
      ]);
    }
    else {
      $rect->translate([-2, -2]);
      for ($i = -2; $i <= 2; $i++) {
        $this->getToolkit()->apply('draw_rectangle', [
          'rectangle' => $rect,
          'border_color' => $i !== 0 ? '#00FF00FF' : '#FF0000FF',
        ]);
        $rect->translate([1, 1]);
      }
      for ($i = 0; $i < 8; $i += 2) {
        $this->getToolkit()->apply('draw_ellipse', [
          'cx' => $points[$i],
          'cy' => $points[$i + 1],
          'width' => 6,
          'height' => 6,
          'color' => '#FF0000FF',
        ]);
      }
    }

    return TRUE;
  }

}
