<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\InterlaceTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Interlace operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_interlace',
  toolkit: 'imagemagick',
  operation: 'interlace',
  label: new TranslatableMarkup('Interlace'),
  description: new TranslatableMarkup('Create an interlaced PNG or GIF or progressive JPEG image.'),
)]
class Interlace extends ImagemagickImageToolkitOperationBase {

  use InterlaceTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArguments(["-interlace", $arguments['type']]);
    return TRUE;
  }

}
