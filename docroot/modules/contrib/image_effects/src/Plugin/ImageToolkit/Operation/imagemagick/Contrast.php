<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ContrastTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Contrast operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_contrast',
  toolkit: 'imagemagick',
  operation: 'contrast',
  label: new TranslatableMarkup('Contrast'),
  description: new TranslatableMarkup('Adjust image contrast.'),
)]
class Contrast extends ImagemagickImageToolkitOperationBase {

  use ContrastTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      $this->addArguments(["-brightness-contrast", "0x{$arguments['level']}"]);
    }

    return TRUE;
  }

}
