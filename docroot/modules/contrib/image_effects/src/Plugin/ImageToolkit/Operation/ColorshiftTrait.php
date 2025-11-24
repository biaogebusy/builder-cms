<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\Color;

/**
 * Base trait for image_effects Colorshift operations.
 */
trait ColorshiftTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'RGB' => [
        'description' => 'The RGB of the color shift.',
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Assure color is a valid hex string.
    if (!Color::validateHex($arguments['RGB'])) {
      throw new \InvalidArgumentException("Invalid color ('{$arguments['RGB']}') specified for the image 'colorshift' operation");
    }

    return $arguments;
  }

}
