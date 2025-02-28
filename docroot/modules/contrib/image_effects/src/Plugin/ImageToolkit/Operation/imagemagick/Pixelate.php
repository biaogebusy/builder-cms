<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\PixelateTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Pixelate operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_pixelate",
 *   toolkit = "imagemagick",
 *   operation = "pixelate",
 *   label = @Translation("Pixelate"),
 *   description = @Translation("Pixelates the image."),
 * )
 */
class Pixelate extends ImagemagickImageToolkitOperationBase {

  use PixelateTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $width = $this->getToolkit()->getWidth();
    $height = $this->getToolkit()->getHeight();

    $this->addArgument('-scale ' . max(1, $width / $arguments['size']) . 'x' . max(1, $height / $arguments['size']));
    $this->addArgument('-scale ' . $width . 'x' . $height);

    return TRUE;
  }

}
