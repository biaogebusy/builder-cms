<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ReplaceImageTrait;

/**
 * Defines Imagemagick image replace operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_replace_image",
 *   toolkit = "imagemagick",
 *   operation = "replace_image",
 *   label = @Translation("Replace image"),
 *   description = @Translation("Replace the current image with another one.")
 * )
 */
class ReplaceImage extends ImagemagickImageToolkitOperationBase {

  use ReplaceImageTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $replacement = $arguments['replacement_image'];

    // Replacement image local path.
    $local_path = $replacement->getToolkit()->ensureSourceLocalPath();
    if ($local_path === '') {
      $source_path = $replacement->getToolkit()->getSource();
      throw new \InvalidArgumentException("Missing local path for image at {$source_path}");
    }

    $this->getToolkit()->arguments()
      ->reset()
      ->setSourceLocalPath($replacement->getToolkit()->ensureSourceLocalPath())
      ->setSourceFormat($replacement->getToolkit()->arguments()->getSourceFormat());
    $this->getToolkit()
      ->setWidth($replacement->getWidth())
      ->setHeight($replacement->getHeight())
      ->setExifOrientation($replacement->getToolkit()->getExifOrientation())
      ->setColorspace($replacement->getToolkit()->getColorspace())
      ->setProfiles($replacement->getToolkit()->getProfiles())
      ->setFrames($replacement->getToolkit()->getFrames());

    return TRUE;
  }

}
