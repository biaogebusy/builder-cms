<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\InterlaceTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Interlace operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_interlace',
  toolkit: 'gd',
  operation: 'interlace',
  label: new TranslatableMarkup('Interlace'),
  description: new TranslatableMarkup('Create an interlaced PNG or GIF or progressive JPEG image.'),
)]
class Interlace extends GDImageToolkitOperationBase {

  use InterlaceTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    return imageinterlace($this->getToolkit()->getImage(), TRUE);
  }

}
