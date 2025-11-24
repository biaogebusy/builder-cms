<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation;

use Drupal\image_effects\Component\ColorUtility;
use Drupal\image_effects\Component\PositionedRectangle;

/**
 * Base trait for draw rectangle operations.
 */
trait DrawRectangleTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'rectangle' => [
        'description' => 'A PositionedRectangle object.',
        'type' => PositionedRectangle::class,
      ],
      'fill_color' => [
        'description' => 'The RGBA color of the polygon fill.',
        'type' => '?string',
        'required' => FALSE,
        'default' => NULL,
      ],
      'fill_color_luma' => [
        'description' => 'If TRUE, convert RGBA of the polygon fill to best match using luma.',
        'type' => 'bool',
        'required' => FALSE,
        'default' => FALSE,
      ],
      'border_color' => [
        'description' => 'The RGBA color of the polygon line.',
        'type' => '?string',
        'required' => FALSE,
        'default' => NULL,
      ],
      'border_color_luma' => [
        'description' => 'If TRUE, convert RGBA of the polygon line to best match using luma.',
        'type' => 'bool',
        'required' => FALSE,
        'default' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments = ArgumentsTypeValidator::validate($this->arguments(), $arguments);

    // Match color luma.
    if ($arguments['fill_color'] && $arguments['fill_color_luma']) {
      $arguments['fill_color'] = ColorUtility::matchLuma($arguments['fill_color']);
    }
    if ($arguments['border_color'] && $arguments['border_color_luma']) {
      $arguments['border_color'] = ColorUtility::matchLuma($arguments['border_color']);
    }

    return $arguments;
  }

}
