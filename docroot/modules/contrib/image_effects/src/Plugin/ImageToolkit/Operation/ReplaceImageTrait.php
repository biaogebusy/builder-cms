<?php

declare(strict_types=1);

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
        'type' => ImageInterface::class,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Ensure replacement_image is a valid image.
    if (!$arguments['replacement_image']->isValid()) {
      $source = $arguments['replacement_image']->getSource();
      throw new \InvalidArgumentException("Invalid image at {$source}");
    }

    return $arguments;
  }

}
