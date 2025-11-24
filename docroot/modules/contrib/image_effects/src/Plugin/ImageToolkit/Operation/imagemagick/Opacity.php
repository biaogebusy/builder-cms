<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\OpacityTrait;
use Drupal\imagemagick\PackageSuite;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick Opacity operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_opacity',
  toolkit: 'imagemagick',
  operation: 'opacity',
  label: new TranslatableMarkup('Opacity'),
  description: new TranslatableMarkup('Adjust image transparency.'),
)]
class Opacity extends ImagemagickImageToolkitOperationBase {

  use OpacityTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getExecManager()->getPackageSuite() === PackageSuite::Graphicsmagick) {
      // GraphicsMagick does not support -alpha argument, return early.
      // @todo implement a GraphicsMagick solution if possible.
      return FALSE;
    }

    switch ($arguments['opacity']) {
      case 100:
        // Fully opaque, leave image as-is.
        break;

      case 0:
        // Fully transparent, set full transparent for all pixels.
        $this->addArguments(["-alpha", "set", "-channel", "Alpha", "-evaluate", "Set", "0%"]);
        break;

      default:
        // Divide existing alpha to the opacity needed. This preserves
        // partially transparent images.
        $divide = number_format((float) (100 / $arguments['opacity']), 4, '.', ',');
        $this->addArguments(["-alpha", "set", "-channel", "Alpha", "-evaluate", "Divide", $divide]);
        break;

    }

    return TRUE;
  }

}
