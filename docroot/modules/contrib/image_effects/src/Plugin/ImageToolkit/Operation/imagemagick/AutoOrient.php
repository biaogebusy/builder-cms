<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick AutoOrient operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_auto_orient",
 *   toolkit = "imagemagick",
 *   operation = "auto_orient",
 *   label = @Translation("Auto orient image"),
 *   description = @Translation("Automatically adjusts the orientation of an image.")
 * )
 */
class AutoOrient extends ImagemagickImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    // This operation does not use any parameters.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArgument('-auto-orient');
    // Swap toolkit's height and width when picture orientation is vertical.
    if (in_array($this->getToolkit()->getExifOrientation(), [5, 6, 7, 8])) {
      $tmp = $this->getToolkit()->getWidth();
      $this->getToolkit()->setWidth($this->getToolkit()->getHeight());
      $this->getToolkit()->setHeight($tmp);
      $this->getToolkit()->setExifOrientation(NULL);
    }
    return TRUE;
  }

}
