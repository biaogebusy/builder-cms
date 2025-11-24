<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawLineTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 draw line operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_draw_line',
  toolkit: 'gd', operation: 'draw_line',
  label: new TranslatableMarkup('Draw line'),
  description: new TranslatableMarkup('Draws on the image a line of the specified color.'),
)]
class DrawLine extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use DrawLineTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($this->getToolkit()->getImage(), $arguments['color']);
    return imageline($this->getToolkit()->getImage(), $arguments['x1'], $arguments['y1'], $arguments['x2'], $arguments['y2'], $color);
  }

}
