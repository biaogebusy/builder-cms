<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\PixelateTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 Pixelate operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_pixelate',
  toolkit: 'gd',
  operation: 'pixelate',
  label: new TranslatableMarkup('Pixelate'),
  description: new TranslatableMarkup('Pixelates the image.'),
)]
class Pixelate extends GDImageToolkitOperationBase {

  use PixelateTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    return imagefilter($this->getToolkit()->getImage(), IMG_FILTER_PIXELATE, $arguments['size'], TRUE);
  }

}
