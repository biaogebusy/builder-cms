<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\GaussianBlurTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Gaussian Blur operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_gd_gaussian_blur',
  toolkit: 'gd',
  operation: 'gaussian_blur',
  label: new TranslatableMarkup('Gaussian blur'),
  description: new TranslatableMarkup('Blur the image with a Gaussian operator.'),
)]
class GaussianBlur extends GDImageToolkitOperationBase {

  use GaussianBlurTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $blur = $this->imageCopyGaussianBlurred($this->getToolkit()->getImage(), $arguments['radius'], $arguments['sigma']);
    if ($blur) {
      $this->getToolkit()->setImage($blur);
      return TRUE;
    }
    return FALSE;
  }

}
