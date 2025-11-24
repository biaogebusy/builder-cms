<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ScaleAndSmartCropTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 Scale and Smart Crop operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_scale_and_smart_crop',
  toolkit: 'gd',
  operation: 'scale_and_smart_crop',
  label: new TranslatableMarkup('Scale and Smart Crop'),
  description: new TranslatableMarkup('Similar to Scale And Crop, but preserves the portion of the image with the most entropy.'),
)]
class ScaleAndSmartCrop extends GDImageToolkitOperationBase {

  use ScaleAndSmartCropTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments = []) {
    // Don't scale if we don't change the dimensions at all.
    if ($arguments['width'] !== $this->getToolkit()->getWidth() || $arguments['height'] !== $this->getToolkit()->getHeight()) {
      // Don't upscale if the option isn't enabled.
      if ($arguments['upscale'] || ($arguments['width'] <= $this->getToolkit()->getWidth() && $arguments['height'] <= $this->getToolkit()->getHeight())) {
        return $this->getToolkit()->apply('resize', $arguments['resize']) && $this->getToolkit()->apply('smart_crop', $arguments);
      }
    }
    return TRUE;
  }

}
