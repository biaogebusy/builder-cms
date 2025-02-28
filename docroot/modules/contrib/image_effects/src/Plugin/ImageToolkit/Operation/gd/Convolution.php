<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ConvolutionTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Convolution operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_convolution",
 *   toolkit = "gd",
 *   operation = "convolution",
 *   label = @Translation("Convolution"),
 *   description = @Translation("Filter image using convolution.")
 * )
 */
class Convolution extends GDImageToolkitOperationBase {

  use ConvolutionTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if (isset($arguments['kernel']) && isset($arguments['divisor']) && isset($arguments['offset'])) {
      return imageconvolution($this->getToolkit()->getResource(), $arguments['kernel'], $arguments['divisor'], $arguments['offset']);
    }

    return TRUE;
  }

}
