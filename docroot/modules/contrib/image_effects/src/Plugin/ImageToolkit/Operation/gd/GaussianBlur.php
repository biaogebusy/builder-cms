<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\gd;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\GaussianBlurTrait;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Defines GD Gaussian Blur operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_gd_gaussian_blur",
 *   toolkit = "gd",
 *   operation = "gaussian_blur",
 *   label = @Translation("Gaussian blur"),
 *   description = @Translation("Blur the image with a Gaussian operator.")
 * )
 */
class GaussianBlur extends GDImageToolkitOperationBase {

  use GaussianBlurTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $blur = $this->imageCopyGaussianBlurred($this->getToolkit()->getResource(), $arguments['radius'], $arguments['sigma']);
    // @todo remove the is_resource check once Drupal 9 is no longer  supported.
    if ((is_object($blur) && $blur instanceof \GdImage) || is_resource($blur)) {
      $original_resource = $this->getToolkit()->getResource();
      $this->getToolkit()->setResource($blur);
      imagedestroy($original_resource);
      return TRUE;
    }
    return FALSE;
  }

}
