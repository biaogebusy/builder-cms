<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawEllipseTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick draw ellipse operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_draw_ellipse',
  toolkit: 'imagemagick',
  operation: 'draw_ellipse',
  label: new TranslatableMarkup('Draw ellipse'),
  description: new TranslatableMarkup('Draws on the image an ellipse of the specified color.'),
)]
class DrawEllipse extends ImagemagickImageToolkitOperationBase {

  use DrawEllipseTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArguments([
      "-fill",
      $arguments['color'],
      "-draw",
      "ellipse {$arguments['cx']},{$arguments['cy']} {$arguments['width']},{$arguments['height']} 0,360",
    ]);
    return TRUE;
  }

}
