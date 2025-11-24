<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\MirrorTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Mirror operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_mirror',
  toolkit: 'imagemagick',
  operation: 'mirror',
  label: new TranslatableMarkup('Mirror'),
  description: new TranslatableMarkup('Mirror the image horizontally and/or vertically.'),
)]
class Mirror extends ImagemagickImageToolkitOperationBase {

  use MirrorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['x_axis'] === TRUE) {
      $this->addArguments(["-flop"]);
    }
    if ($arguments['y_axis'] === TRUE) {
      $this->addArguments(["-flip"]);
    }
    return TRUE;
  }

}
