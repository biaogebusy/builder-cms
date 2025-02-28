<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MaskTrait;

/**
 * Defines GD Mask operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_mask",
 *   toolkit = "gd",
 *   operation = "mask",
 *   label = @Translation("Mask"),
 *   description = @Translation("Applies a mask to the source image.")
 * )
 */
class Mask extends GDImageToolkitOperationBase {

  use MaskTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $mask = $arguments['mask_image'];
    $x_offset = $arguments['x_offset'];
    $y_offset = $arguments['y_offset'];

    // Resize mask if needed.
    if ($arguments['mask_width'] || $arguments['mask_height']) {
      $mask->apply('resize', ['width' => $arguments['mask_width'], 'height' => $arguments['mask_height']]);
    }

    // Preserves original resource, to be destroyed upon success.
    $original_resource = $this->getToolkit()->getResource();

    // Prepare a new image.
    $data = [
      'width' => $this->getToolkit()->getWidth(),
      'height' => $this->getToolkit()->getHeight(),
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      // In case of failure, destroy the temporary resource and restore
      // the original one.
      imagedestroy($this->getToolkit()->getResource());
      $this->getToolkit()->setResource($original_resource);
      return FALSE;
    }

    // Force a transparent color fill to prevent JPEG to end up as a white
    // mask, while in memory.
    imagefill($this->getToolkit()->getResource(), 0, 0, imagecolorallocatealpha($this->getToolkit()->getResource(), 0, 0, 0, 127));

    // Perform pixel-based alpha map application.
    for ($x = 0; $x < $mask->getToolkit()->getWidth(); $x++) {
      for ($y = 0; $y < $mask->getToolkit()->getHeight(); $y++) {
        // Deal with images with mismatched sizes.
        if ($x + $x_offset >= imagesx($original_resource) || $y + $y_offset >= imagesy($original_resource) || $x + $x_offset < 0 || $y + $y_offset < 0) {
          continue;
        }
        else {
          $alpha = imagecolorsforindex($mask->getToolkit()->getResource(), imagecolorat($mask->getToolkit()->getResource(), $x, $y));
          $alpha = 127 - floor($alpha['red'] / 2);
          $color = imagecolorsforindex($this->getToolkit()->getResource(), imagecolorat($original_resource, $x + $x_offset, $y + $y_offset));
          imagesetpixel($this->getToolkit()->getResource(), $x + $x_offset, $y + $y_offset, imagecolorallocatealpha($this->getToolkit()->getResource(), $color['red'], $color['green'], $color['blue'], $alpha));
        }
      }
    }

    // Destroy original picture.
    imagedestroy($original_resource);

    return TRUE;
  }

}
