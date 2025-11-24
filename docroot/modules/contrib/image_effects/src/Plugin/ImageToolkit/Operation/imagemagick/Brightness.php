<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\BrightnessTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Brightness operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_brightness',
  toolkit: 'imagemagick',
  operation: 'brightness',
  label: new TranslatableMarkup('Brightness'),
  description: new TranslatableMarkup('Adjust image brightness.'),
)]
class Brightness extends ImagemagickImageToolkitOperationBase {

  use BrightnessTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['level']) {
      $this->addArguments(["-brightness-contrast", $arguments['level']]);
    }

    return TRUE;
  }

}
