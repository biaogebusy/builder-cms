<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\BrightnessTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Brightness operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_brightness',
  toolkit: 'gd',
  operation: 'brightness',
  label: new TranslatableMarkup('Brightness'),
  description: new TranslatableMarkup('Adjust image brightness.'),
)]
class Brightness extends GDImageToolkitOperationBase {

  use BrightnessTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      return imagefilter($this->getToolkit()->getImage(), IMG_FILTER_BRIGHTNESS, (int) round($arguments['level'] / 100 * 255));
    }

    return TRUE;
  }

}
