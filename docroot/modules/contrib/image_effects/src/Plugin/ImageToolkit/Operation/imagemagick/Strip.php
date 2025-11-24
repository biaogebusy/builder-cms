<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Strip operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_strip',
  toolkit: 'imagemagick',
  operation: 'strip',
  label: new TranslatableMarkup('Strip'),
  description: new TranslatableMarkup('Strips metadata from an image.'),
)]
class Strip extends ImagemagickImageToolkitOperationBase {

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
    $this->addArguments(['-strip']);
    return TRUE;
  }

}
