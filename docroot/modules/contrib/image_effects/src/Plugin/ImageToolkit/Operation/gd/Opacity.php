<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\OpacityTrait;

/**
 * Defines GD Opacity operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_opacity",
 *   toolkit = "gd",
 *   operation = "opacity",
 *   label = @Translation("Opacity"),
 *   description = @Translation("Adjust image transparency.")
 * )
 */
class Opacity extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use OpacityTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['opacity'] < 100) {
      return $this->filterOpacity($this->getToolkit()->getResource(), $arguments['opacity']);
    }
    return TRUE;
  }

}
