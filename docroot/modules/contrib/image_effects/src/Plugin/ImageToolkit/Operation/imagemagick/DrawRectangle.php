<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\DrawRectangleTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick draw rectangle operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_draw_rectangle',
  toolkit: 'imagemagick',
  operation: 'draw_rectangle',
  label: new TranslatableMarkup('Draw rectangle'),
  description: new TranslatableMarkup('Draws  a rectangle on the image, optionally filling it in with a specified color.'),
)]
class DrawRectangle extends ImagemagickImageToolkitOperationBase {

  use DrawRectangleTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['fill_color']) {
      $arg = ['-fill', $arguments['fill_color']];
    }
    else {
      $arg = ['-fill', 'none'];
    }
    if ($arguments['border_color']) {
      $arg[] = '-stroke';
      $arg[] = $arguments['border_color'];
      $arg[] = '-strokewidth';
      $arg[] = '1';
    }
    $this->addArguments($arg);
    $a = $arguments['rectangle']->getPoint('c_a');
    $b = $arguments['rectangle']->getPoint('c_b');
    $c = $arguments['rectangle']->getPoint('c_c');
    $d = $arguments['rectangle']->getPoint('c_d');
    $this->addArguments(["-draw", "polygon {$d[0]},{$d[1]} {$c[0]},{$c[1]} {$b[0]},{$b[1]} {$a[0]},{$a[1]}"]);
    return TRUE;
  }

}
