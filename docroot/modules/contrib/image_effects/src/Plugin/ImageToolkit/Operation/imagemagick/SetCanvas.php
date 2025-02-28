<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\SetCanvasTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick set canvas operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_set_canvas",
 *   toolkit = "imagemagick",
 *   operation = "set_canvas",
 *   label = @Translation("Set canvas"),
 *   description = @Translation("Lay the image over a colored canvas.")
 * )
 */
class SetCanvas extends ImagemagickImageToolkitOperationBase {

  use ImagemagickOperationTrait;
  use SetCanvasTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $toolkit_arguments = $this->getToolkit()->arguments();

    // Calculate geometry.
    $geometry = sprintf('%dx%d', $arguments['width'], $arguments['height']);
    if ($arguments['x_pos'] || $arguments['y_pos']) {
      $geometry .= sprintf('%+d%+d', -$arguments['x_pos'], -$arguments['y_pos']);
    }

    // Determine background.
    if ($arguments['canvas_color']) {
      $bg = '-background ' . $this->escapeArgument($arguments['canvas_color']);
    }
    else {
      $format = $toolkit_arguments->getDestinationFormat() ?: $toolkit_arguments->getSourceFormat();
      $mime_type = $this->getFormatMapper()->getMimeTypeFromFormat($format);
      if ($mime_type === 'image/jpeg') {
        // JPEG does not allow transparency. Set to white.
        // @todo allow to be configurable.
        $bg = '-background ' . $this->escapeArgument('#FFFFFF');
      }
      else {
        $bg = '-background transparent';
      }
    }

    // Add argument.
    $this->addArgument("-gravity none {$bg} -compose src-over -extent {$geometry}");

    // Set dimensions.
    $this->getToolkit()
      ->setWidth($arguments['width'])
      ->setHeight($arguments['height']);

    return TRUE;
  }

}
