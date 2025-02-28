<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ImagemagickArgumentsTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick 'Imagemagick arguments' operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_imagemagick_arguments",
 *   toolkit = "imagemagick",
 *   operation = "imagemagick_arguments",
 *   label = @Translation("ImageMagick arguments"),
 *   description = @Translation("Directly execute ImageMagick command line arguments.")
 * )
 */
class ImagemagickArguments extends ImagemagickImageToolkitOperationBase {

  use ImagemagickArgumentsTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Add argument.
    $this->addArgument($arguments['command_line']);

    // Set dimensions.
    $this->getToolkit()
      ->setWidth($arguments['width'])
      ->setHeight($arguments['height']);

    return TRUE;
  }

}
