<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetGifTransparentColorTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick set_gif_transparent_color image operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_set_gif_transparent_color",
 *   toolkit = "imagemagick",
 *   operation = "set_gif_transparent_color",
 *   label = @Translation("Set the image transparent color"),
 *   description = @Translation("Set the image transparent color for GIF images.")
 * )
 */
class SetGifTransparentColor extends ImagemagickImageToolkitOperationBase {

  use ImagemagickOperationTrait;
  use SetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $toolkit_arguments = $this->getToolkit()->arguments();
    $format = $toolkit_arguments->getDestinationFormat() ?: $toolkit_arguments->getSourceFormat();
    $mime_type = $this->getFormatMapper()->getMimeTypeFromFormat($format);
    if ($mime_type === 'image/gif' && $arguments['transparent_color']) {
      $find = $toolkit_arguments->find('/^\-alpha off \-transparent\-color/');
      if (!empty($find)) {
        reset($find);
        $toolkit_arguments->remove(key($find));
      }
      $this->addArgument('-alpha off -transparent-color ' . $this->escapeArgument($arguments['transparent_color']) . ' -transparent ' . $this->escapeArgument($arguments['transparent_color']));
    }
  }

}
