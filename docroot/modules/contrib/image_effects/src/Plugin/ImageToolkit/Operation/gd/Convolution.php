<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ConvolutionTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Convolution operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_convolution',
  toolkit: 'gd',
  operation: 'convolution',
  label: new TranslatableMarkup('Convolution'),
  description: new TranslatableMarkup('Filter image using convolution.'),
)]
class Convolution extends GDImageToolkitOperationBase {

  use ConvolutionTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if (isset($arguments['kernel']) && isset($arguments['divisor']) && isset($arguments['offset'])) {
      return imageconvolution($this->getToolkit()->getImage(), $arguments['kernel'], $arguments['divisor'], $arguments['offset']);
    }

    return TRUE;
  }

}
