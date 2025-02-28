<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawEllipseTrait;

/**
 * Defines ImageMagick draw ellipse operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_draw_ellipse",
 *   toolkit = "imagemagick",
 *   operation = "draw_ellipse",
 *   label = @Translation("Draw ellipse"),
 *   description = @Translation("Draws on the image an ellipse of the specified color.")
 * )
 */
class DrawEllipse extends ImagemagickImageToolkitOperationBase {

  use DrawEllipseTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $arg = '';
    $arg .= '-fill ' . $this->escapeArgument($arguments['color']);
    $this->addArgument($arg . ' -draw ' . $this->escapeArgument("ellipse {$arguments['cx']},{$arguments['cy']} {$arguments['width']},{$arguments['height']} 0,360"));
    return TRUE;
  }

}
