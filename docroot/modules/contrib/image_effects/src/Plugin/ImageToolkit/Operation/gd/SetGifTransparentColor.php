<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetGifTransparentColorTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 set_gif_transparent_color image operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_set_gif_transparent_color',
  toolkit: 'gd',
  operation: 'set_gif_transparent_color',
  label: new TranslatableMarkup('Set the image transparent color'),
  description: new TranslatableMarkup('Set the image transparent color for GIF images.'),
)]
class SetGifTransparentColor extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use SetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getType() == IMAGETYPE_GIF && $arguments['transparent_color']) {
      $rgb = Color::hexToRgb($arguments['transparent_color']);
      $color = imagecolorallocate($this->getToolkit()->getImage(), $rgb['red'], $rgb['green'], $rgb['blue']);
      imagecolortransparent($this->getToolkit()->getImage(), $color);
    }
    return TRUE;
  }

}
