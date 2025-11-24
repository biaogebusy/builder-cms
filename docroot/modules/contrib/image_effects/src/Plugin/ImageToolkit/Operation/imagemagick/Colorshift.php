<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ColorshiftTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Colorshift operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_colorshift',
  toolkit: 'imagemagick',
  operation: 'colorshift',
  label: new TranslatableMarkup('Colorshift'),
  description: new TranslatableMarkup('Shift image colors.'),
)]
class Colorshift extends ImagemagickImageToolkitOperationBase {

  use ColorshiftTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $this->addArguments(["+level-colors", "{$arguments['RGB']},white"]);
    return TRUE;
  }

}
