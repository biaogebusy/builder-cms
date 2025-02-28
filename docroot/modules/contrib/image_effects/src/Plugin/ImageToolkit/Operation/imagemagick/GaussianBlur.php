<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\GaussianBlurTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Gaussian Blur operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_gaussian_blur",
 *   toolkit = "imagemagick",
 *   operation = "gaussian_blur",
 *   label = @Translation("Gaussian blur"),
 *   description = @Translation("Blur the image with a Gaussian operator.")
 * )
 */
class GaussianBlur extends ImagemagickImageToolkitOperationBase {

  use GaussianBlurTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    switch ($this->getToolkit()->getExecManager()->getPackage()) {
      case 'imagemagick':
        $op = '-channel RGBA -blur ' . $arguments['radius'];
        break;

      case 'graphicsmagick':
        $op = '-gaussian ' . $arguments['radius'];
        break;

    }
    $sigma = $arguments['sigma'] !== NULL ? $arguments['sigma'] : $arguments['radius'] / 3 * 2;
    $op .= 'x' . number_format($sigma, 1, '.', '');
    $this->addArgument($op);
    return TRUE;
  }

}
