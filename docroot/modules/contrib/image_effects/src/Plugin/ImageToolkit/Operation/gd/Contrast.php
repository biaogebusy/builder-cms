<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ContrastTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Contrast operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_contrast',
  toolkit: 'gd',
  operation: 'contrast',
  label: new TranslatableMarkup('Contrast'),
  description: new TranslatableMarkup('Adjust image contrast.'),
)]
class Contrast extends GDImageToolkitOperationBase {

  use ContrastTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      return imagefilter($this->getToolkit()->getImage(), IMG_FILTER_CONTRAST, $arguments['level'] * -1);
    }

    return TRUE;
  }

}
