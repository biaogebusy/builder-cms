<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MirrorTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Mirror operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_mirror',
  toolkit: 'gd',
  operation: 'mirror',
  label: new TranslatableMarkup('Mirror'),
  description: new TranslatableMarkup('Mirror the image horizontally and/or vertically.'),
)]
class Mirror extends GDImageToolkitOperationBase {

  use MirrorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['x_axis'] === TRUE && $arguments['y_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getImage(), IMG_FLIP_BOTH);
    }
    elseif ($arguments['x_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getImage(), IMG_FLIP_HORIZONTAL);
    }
    elseif ($arguments['y_axis'] === TRUE) {
      return imageflip($this->getToolkit()->getImage(), IMG_FLIP_VERTICAL);
    }
    return FALSE;
  }

}
