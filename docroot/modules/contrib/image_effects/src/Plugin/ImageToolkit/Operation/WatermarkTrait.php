<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Core\Image\ImageInterface;

/**
 * Base trait for image_effects Watermark operations.
 */
trait WatermarkTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'watermark_image' => [
        'description' => 'Watermark image.',
      ],
      'watermark_width' => [
        'description' => 'Width of watermark image.',
        'required' => FALSE,
        'default' => NULL,
      ],
      'watermark_height' => [
        'description' => 'Height of watermark image.',
        'required' => FALSE,
        'default' => NULL,
      ],
      'x_offset' => [
        'description' => 'X offset for watermark image.',
        'required' => FALSE,
        'default' => 0,
      ],
      'y_offset' => [
        'description' => 'Y offset for watermark image.',
        'required' => FALSE,
        'default' => 0,
      ],
      'opacity' => [
        'description' => 'Opacity for watermark image.',
        'required' => FALSE,
        'default' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure watermark_image opacity is in the range 0-100.
    if ($arguments['opacity'] > 100 || $arguments['opacity'] < 0) {
      throw new \InvalidArgumentException("Invalid opacity ('{$arguments['opacity']}') specified for the image 'watermark' operation");
    }
    // Ensure watermark_image is an expected ImageInterface object.
    if (!$arguments['watermark_image'] instanceof ImageInterface) {
      throw new \InvalidArgumentException("Watermark image passed to the 'watermark' operation is invalid");
    }
    // Ensure watermark_image is a valid image.
    if (!$arguments['watermark_image']->isValid()) {
      $source = $arguments['watermark_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }
    // Ensure 'watermark_width' is NULL or a positive integer.
    $arguments['watermark_width'] = $arguments['watermark_width'] !== NULL ? (int) $arguments['watermark_width'] : NULL;
    if ($arguments['watermark_width'] !== NULL && $arguments['watermark_width'] <= 0) {
      throw new \InvalidArgumentException("Invalid watermark width ('{$arguments['watermark_width']}') specified for the image 'watermark' operation");
    }
    // Ensure 'watermark_height' is NULL or a positive integer.
    $arguments['watermark_height'] = $arguments['watermark_height'] !== NULL ? (int) $arguments['watermark_height'] : NULL;
    if ($arguments['watermark_height'] !== NULL && $arguments['watermark_height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['watermark_height']}') specified for the image 'watermark' operation");
    }
    // Ensure 'x_offset' is an integer.
    $arguments['x_offset'] = (int) $arguments['x_offset'];
    // Ensure 'y_offset' is an integer.
    $arguments['y_offset'] = (int) $arguments['y_offset'];
    return $arguments;
  }

}
