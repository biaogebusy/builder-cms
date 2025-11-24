<?php

declare(strict_types=1);

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
        'type' => ImageInterface::class,
      ],
      'watermark_width' => [
        'description' => 'Width of watermark image.',
        'type' => '?int',
        'required' => FALSE,
        'default' => NULL,
      ],
      'watermark_height' => [
        'description' => 'Height of watermark image.',
        'type' => '?int',
        'required' => FALSE,
        'default' => NULL,
      ],
      'x_offset' => [
        'description' => 'X offset for watermark image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 0,
      ],
      'y_offset' => [
        'description' => 'Y offset for watermark image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 0,
      ],
      'opacity' => [
        'description' => 'Opacity for watermark image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 100,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure watermark_image opacity is in the range 0-100.
    if ($arguments['opacity'] > 100 || $arguments['opacity'] < 0) {
      throw new \InvalidArgumentException("Invalid opacity ('{$arguments['opacity']}') specified for the image 'watermark' operation");
    }
    // Ensure watermark_image is a valid image.
    if (!$arguments['watermark_image']->isValid()) {
      $source = $arguments['watermark_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }
    // Ensure 'watermark_width' is NULL or a positive integer.
    if ($arguments['watermark_width'] !== NULL && $arguments['watermark_width'] <= 0) {
      throw new \InvalidArgumentException("Invalid watermark width ('{$arguments['watermark_width']}') specified for the image 'watermark' operation");
    }
    // Ensure 'watermark_height' is NULL or a positive integer.
    if ($arguments['watermark_height'] !== NULL && $arguments['watermark_height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['watermark_height']}') specified for the image 'watermark' operation");
    }

    return $arguments;
  }

}
