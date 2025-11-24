<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\OpacityTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Opacity operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_opacity',
  toolkit: 'gd',
  operation: 'opacity',
  label: new TranslatableMarkup('Opacity'),
  description: new TranslatableMarkup('Adjust image transparency.'),
)]
class Opacity extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use OpacityTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['opacity'] < 100) {
      return $this->filterOpacity($this->getToolkit()->getImage(), $arguments['opacity']);
    }
    return TRUE;
  }

}
