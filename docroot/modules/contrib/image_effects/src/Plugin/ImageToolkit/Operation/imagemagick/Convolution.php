<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ConvolutionTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Convolution operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_convolution',
  toolkit: 'imagemagick',
  operation: 'convolution',
  label: new TranslatableMarkup('Convolution'),
  description: new TranslatableMarkup('Adjust image convolution.'),
)]
class Convolution extends ImagemagickImageToolkitOperationBase {

  use ConvolutionTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if (isset($arguments['kernel']) && isset($arguments['divisor']) && isset($arguments['offset'])) {
      $matrix_s = '';
      foreach ($arguments['kernel'] as $vector) {
        $vector = array_map(function ($e) use ($arguments) {
          return ($e / $arguments['divisor']) + $arguments['offset'];
        }, $vector);
        $matrix_s .= implode(',', $vector) . " ";
      }
      $matrix_s = substr($matrix_s, 0, -1);
      $this->addArguments(["-morphology", "Convolve", "3x3:{$matrix_s}"]);
    }

    return TRUE;
  }

}
