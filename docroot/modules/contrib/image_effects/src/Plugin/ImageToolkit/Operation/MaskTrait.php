<?php

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
      ],
      'mask_width' => [
        'description' => 'Width of mask image.',
        'required' => FALSE,
        'default' => NULL,
      ],
      'mask_height' => [
        'description' => 'Height of mask image.',
        'required' => FALSE,
        'default' => NULL,
      ],
      'x_offset' => [
        'description' => 'X offset for mask image.',
        'required' => FALSE,
        'default' => 0,
      ],
      'y_offset' => [
        'description' => 'Y offset for mask image.',
        'required' => FALSE,
        'default' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure mask_image is an expected ImageInterface object.
    if (!$arguments['mask_image'] instanceof ImageInterface) {
      throw new \InvalidArgumentException("Mask image passed to the 'mask' operation is invalid");
    }
    // Ensure mask_image is a valid image.
    if (!$arguments['mask_image']->isValid()) {
      $source = $arguments['mask_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }
    // Ensure 'mask_width' is NULL or a positive integer.
    $arguments['mask_width'] = $arguments['mask_width'] !== NULL ? (int) $arguments['mask_width'] : NULL;
    if ($arguments['mask_width'] !== NULL && $arguments['mask_width'] <= 0) {
      throw new \InvalidArgumentException("Invalid mask width ('{$arguments['mask_width']}') specified for the image 'mask' operation");
    }
    // Ensure 'mask_height' is NULL or a positive integer.
    $arguments['mask_height'] = $arguments['mask_height'] !== NULL ? (int) $arguments['mask_height'] : NULL;
    if ($arguments['mask_height'] !== NULL && $arguments['mask_height'] <= 0) {
      throw new \InvalidArgumentException("Invalid height ('{$arguments['mask_height']}') specified for the image 'mask' operation");
    }
    // Ensure 'x_offset' is an integer.
    $arguments['x_offset'] = (int) $arguments['x_offset'];
    // Ensure 'y_offset' is an integer.
    $arguments['y_offset'] = (int) $arguments['y_offset'];
    return $arguments;
  }

}
