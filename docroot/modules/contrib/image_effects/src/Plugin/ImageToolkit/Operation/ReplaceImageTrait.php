<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Core\Image\ImageInterface;

/**
 * Base trait for replace image operations.
 */
trait ReplaceImageTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'replacement_image' => [
        'description' => 'The image to be used to replace current one.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure replacement_image is an expected ImageInterface object.
    if (!$arguments['replacement_image'] instanceof ImageInterface) {
      throw new \InvalidArgumentException("Replacement image passed to the 'replace_image' operation is invalid");
    }
    // Ensure replacement_image is a valid image.
    if (!$arguments['replacement_image']->isValid()) {
      $source = $arguments['replacement_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }
    return $arguments;
  }

}
