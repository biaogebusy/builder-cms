<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Component\PositionedRectangle;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetCanvasTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 set canvas operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_set_canvas",
 *   toolkit = "gd",
 *   operation = "set_canvas",
 *   label = @Translation("Set canvas"),
 *   description = @Translation("Lay the image over a colored canvas.")
 * )
 */
class SetCanvas extends GDImageToolkitOperationBase {

  use SetCanvasTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Store the original resource.
    $original_res = $this->getToolkit()->getResource();

    // Prepare the canvas.
    $data = [
      'width' => $arguments['width'],
      'height' => $arguments['height'],
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Fill the canvas with required color.
    $data = [
      'rectangle' => new PositionedRectangle($arguments['width'], $arguments['height']),
      'fill_color' => $arguments['canvas_color'],
    ];
    if (!$this->getToolkit()->apply('draw_rectangle', $data)) {
      return FALSE;
    }

    // Overlay the current image on the canvas.
    imagealphablending($original_res, TRUE);
    imagesavealpha($original_res, TRUE);
    imagealphablending($this->getToolkit()->getResource(), TRUE);
    imagesavealpha($this->getToolkit()->getResource(), TRUE);
    if (imagecopy($this->getToolkit()->getResource(), $original_res, $arguments['x_pos'], $arguments['y_pos'], 0, 0, imagesx($original_res), imagesy($original_res))) {
      imagedestroy($original_res);
      return TRUE;
    }
    else {
      // In case of failure, destroy the temporary resource and restore
      // the original one.
      imagedestroy($this->getToolkit()->getResource());
      $this->getToolkit()->setResource($original_res);
    }
    return FALSE;
  }

}
