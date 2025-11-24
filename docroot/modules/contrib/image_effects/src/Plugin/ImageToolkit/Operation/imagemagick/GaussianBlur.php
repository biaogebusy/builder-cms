<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\GaussianBlurTrait;
use Drupal\imagemagick\PackageSuite;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Gaussian Blur operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_gaussian_blur',
  toolkit: 'imagemagick',
  operation: 'gaussian_blur',
  label: new TranslatableMarkup('Gaussian blur'),
  description: new TranslatableMarkup('Blur the image with a Gaussian operator.'),
)]
class GaussianBlur extends ImagemagickImageToolkitOperationBase {

  use GaussianBlurTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $sigma = $arguments['sigma'] ?? $arguments['radius'] / 3 * 2;
    $op = match ($this->getToolkit()->getExecManager()->getPackageSuite()) {
      PackageSuite::Imagemagick => ['-channel', 'RGBA', '-blur'],
      PackageSuite::Graphicsmagick => ['-gaussian'],
    };
    $op[] = $arguments['radius'] . 'x' . number_format($sigma, 1, '.', '');
    $this->addArguments($op);
    return TRUE;
  }

}
