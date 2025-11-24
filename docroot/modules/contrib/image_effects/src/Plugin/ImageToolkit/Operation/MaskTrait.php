<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Core\Image\ImageInterface;

/**
 * Base trait for image_effects Mask operations.
 */
trait MaskTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'mask_image' => [
        'description' => 'Mask image.',
        'type' => ImageInterface::class,
      ],
      'mask_width' => [
        'description' => 'Width of mask image.',
        'type' => '?int',
        'required' => FALSE,
        'default' => NULL,
      ],
      'mask_height' => [
        'description' => 'Height of mask image.',
        'type' => '?int',
        'required' => FALSE,
        'default' => NULL,
      ],
      'x_offset' => [
        'description' => 'X offset for mask image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 0,
      ],
      'y_offset' => [
        'description' => 'Y offset for mask image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure mask_image is a valid image.
    if (!$arguments['mask_image']->isValid()) {
      $source = $arguments['mask_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }
    // Ensure 'mask_width' is NULL or a positive integer.
    if ($arguments['mask_width'] !== NULL && $arguments['mask_width'] <= 0) {
      throw new \InvalidArgumentException("Invalid mask width ('{$arguments['mask_width']}') specified for the image 'mask' operation");
    }
    // Ensure 'mask_height' is NULL or a positive integer.
    if ($arguments['mask_height'] !== NULL && $arguments['mask_height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['mask_height']}') specified for the image 'mask' operation");
    }

    return $arguments;
  }

}
