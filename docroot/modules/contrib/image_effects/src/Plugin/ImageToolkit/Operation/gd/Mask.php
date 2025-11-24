<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MaskTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Mask operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_mask',
  toolkit: 'gd',
  operation: 'mask',
  label: new TranslatableMarkup('Mask'),
  description: new TranslatableMarkup('Applies a mask to the source image.'),
)]
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

    // Preserves original image.
    $original_image = $this->getToolkit()->getImage();

    // Prepare a new image.
    $data = [
      'width' => $this->getToolkit()->getWidth(),
      'height' => $this->getToolkit()->getHeight(),
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      // In case of failure, restore the original image.
      $this->getToolkit()->setImage($original_image);
      return FALSE;
    }

    // Force a transparent color fill to prevent JPEG to end up as a white
    // mask, while in memory.
    imagefill($this->getToolkit()->getImage(), 0, 0, imagecolorallocatealpha($this->getToolkit()->getImage(), 0, 0, 0, 127));

    // Perform pixel-based alpha map application.
    for ($x = 0; $x < $mask->getToolkit()->getWidth(); $x++) {
      for ($y = 0; $y < $mask->getToolkit()->getHeight(); $y++) {
        // Deal with images with mismatched sizes.
        if ($x + $x_offset >= imagesx($original_image) || $y + $y_offset >= imagesy($original_image) || $x + $x_offset < 0 || $y + $y_offset < 0) {
          continue;
        }
        else {
          $alpha = imagecolorsforindex($mask->getToolkit()->getImage(), imagecolorat($mask->getToolkit()->getImage(), $x, $y));
          $alpha = 127 - (int) floor($alpha['red'] / 2);
          $color = imagecolorsforindex($this->getToolkit()->getImage(), imagecolorat($original_image, $x + $x_offset, $y + $y_offset));
          imagesetpixel($this->getToolkit()->getImage(), $x + $x_offset, $y + $y_offset, imagecolorallocatealpha($this->getToolkit()->getImage(), $color['red'], $color['green'], $color['blue'], $alpha));
        }
      }
    }

    return TRUE;
  }

}
