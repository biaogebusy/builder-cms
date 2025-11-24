<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawEllipseTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD2 draw ellipse operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_draw_ellipse',
  toolkit: 'gd',
  operation: 'draw_ellipse',
  label: new TranslatableMarkup('Draw ellipse'),
  description: new TranslatableMarkup('Draws on the image an ellipse of the specified color.'),
)]
class DrawEllipse extends GDImageToolkitOperationBase {

  use GDOperationTrait;
  use DrawEllipseTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($this->getToolkit()->getImage(), $arguments['color']);
    return imagefilledellipse($this->getToolkit()->getImage(), $arguments['cx'], $arguments['cy'], $arguments['width'], $arguments['height'], $color);
  }

}
