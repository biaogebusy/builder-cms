<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Core\Image\ImageInterface;

/**
 * Base trait for image_effects Background operations.
 */
trait BackgroundTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'x_offset' => [
        'description' => 'X offset for source image.',
        'type' => 'int',
      ],
      'y_offset' => [
        'description' => 'Y offset for source image.',
        'type' => 'int',
      ],
      'opacity' => [
        'description' => 'Opacity for source image.',
        'type' => 'int',
        'required' => FALSE,
        'default' => 100,
      ],
      'background_image' => [
        'description' => 'Image to use for background.',
        'type' => ImageInterface::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure source image opacity is in the range 0-100.
    if ($arguments['opacity'] > 100 || $arguments['opacity'] < 0) {
      throw new \InvalidArgumentException("Invalid opacity ('{$arguments['opacity']}') specified for the image 'background' operation");
    }
    // Ensure background_image is a valid image.
    if (!$arguments['background_image']->isValid()) {
      $source = $arguments['background_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }

    return $arguments;
  }

}
