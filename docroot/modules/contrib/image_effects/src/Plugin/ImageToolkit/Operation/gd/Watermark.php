<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\WatermarkTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Watermark operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_watermark',
  toolkit: 'gd',
  operation: 'watermark',
  label: new TranslatableMarkup('Watermark'),
  description: new TranslatableMarkup('Add watermark image effect.'),
)]
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
      $this->getToolkit()->getImage(),
      $watermark->getToolkit()->getImage(),
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
