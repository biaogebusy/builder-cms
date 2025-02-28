<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\BackgroundTrait;

/**
 * Defines GD Background operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_background",
 *   toolkit = "gd",
 *   operation = "background",
 *   label = @Translation("Background"),
 *   description = @Translation("Places the source image over a background image.")
 * )
 */
class Background extends GDImageToolkitOperationBase {

  use BackgroundTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Preserves original resource, to be destroyed upon success.
    $original_resource = $this->getToolkit()->getResource();

    // Prepare a new image.
    $data = [
      'width' => $arguments['background_image']->getWidth(),
      'height' => $arguments['background_image']->getHeight(),
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

    // Overlay background at 0,0.
    $success = $this->imageCopyMergeAlpha(
      $this->getToolkit()->getResource(),
      $arguments['background_image']->getToolkit()->getResource(),
      0,
      0,
      0,
      0,
      $arguments['background_image']->getWidth(),
      $arguments['background_image']->getHeight(),
      100
    );
    if (!$success) {
      // In case of failure, destroy the temporary resource and restore
      // the original one.
      imagedestroy($this->getToolkit()->getResource());
      $this->getToolkit()->setResource($original_resource);
      return FALSE;
    }

    // Overlay original source at offset.
    $success = $this->imageCopyMergeAlpha(
      $this->getToolkit()->getResource(),
      $original_resource,
      $arguments['x_offset'],
      $arguments['y_offset'],
      0,
      0,
      imagesx($original_resource),
      imagesy($original_resource),
      $arguments['opacity']
    );
    if ($success) {
      imagedestroy($original_resource);
    }
    else {
      imagedestroy($this->getToolkit()->getResource());
      $this->getToolkit()->setResource($original_resource);
    }
    return $success;
  }

}
