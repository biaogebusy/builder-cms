<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\WatermarkTrait;

/**
 * Defines ImageMagick Watermark operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_watermark",
 *   toolkit = "imagemagick",
 *   operation = "watermark",
 *   label = @Translation("Watermark"),
 *   description = @Translation("Add watermark image effect.")
 * )
 */
class Watermark extends ImagemagickImageToolkitOperationBase {

  use WatermarkTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Watermark image local path.
    $local_path = $arguments['watermark_image']->getToolkit()->ensureSourceLocalPath();
    if ($local_path !== '') {
      $image_path = $this->escapeArgument($local_path);
    }
    else {
      $source_path = $arguments['watermark_image']->getToolkit()->getSource();
      throw new \InvalidArgumentException("Missing local path for image at {$source_path}");
    }

    // Set the dimensions of the overlay.
    $w = $arguments['watermark_width'] ?: $arguments['watermark_image']->getToolkit()->getWidth();
    $h = $arguments['watermark_height'] ?: $arguments['watermark_image']->getToolkit()->getHeight();

    // Set offset. Offset arguments require a sign in front.
    $x = $arguments['x_offset'] >= 0 ? ('+' . $arguments['x_offset']) : $arguments['x_offset'];
    $y = $arguments['y_offset'] >= 0 ? ('+' . $arguments['y_offset']) : $arguments['y_offset'];

    // Compose it with the destination.
    switch ($this->getToolkit()->getExecManager()->getPackage()) {
      case 'imagemagick':
        if ($arguments['opacity'] == 100) {
          $op = "-gravity None {$image_path} -geometry {$w}x{$h}!{$x}{$y} -compose src-over -composite";
        }
        else {
          $op = "-gravity None {$image_path} -geometry {$w}x{$h}!{$x}{$y} -compose blend -define compose:args={$arguments['opacity']} -composite";
        }
        break;

      case 'graphicsmagick':
        // @todo see if GraphicsMagick can support opacity setting.
        $op = "-draw 'image Over {$arguments['x_offset']},{$arguments['y_offset']} {$w},{$h} {$image_path}'";
        break;

    }

    $this->addArgument($op);
    return TRUE;
  }

}
