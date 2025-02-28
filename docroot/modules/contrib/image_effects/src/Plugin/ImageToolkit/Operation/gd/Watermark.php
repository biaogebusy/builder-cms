<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\WatermarkTrait;

/**
 * Defines GD Watermark operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_watermark",
 *   toolkit = "gd",
 *   operation = "watermark",
 *   label = @Translation("Watermark"),
 *   description = @Translation("Add watermark image effect.")
 * )
 */
class Watermark extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use WatermarkTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $watermark = $arguments['watermark_image'];

    // Resize watermark if needed.
    if ($arguments['watermark_width'] || $arguments['watermark_height']) {
      $watermark->apply('resize', ['width' => $arguments['watermark_width'], 'height' => $arguments['watermark_height']]);
    }

    return $this->imageCopyMergeAlpha(
      $this->getToolkit()->getResource(),
      $watermark->getToolkit()->getResource(),
      $arguments['x_offset'],
      $arguments['y_offset'],
      0,
      0,
      $watermark->getToolkit()->getWidth(),
      $watermark->getToolkit()->getHeight(),
      $arguments['opacity']
    );
  }

}
