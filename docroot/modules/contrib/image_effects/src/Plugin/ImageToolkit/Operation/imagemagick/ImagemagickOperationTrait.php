<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\ImagemagickFormatMapperInterface;

/**
 * Trait for ImageMagick image toolkit operations.
 */
trait ImagemagickOperationTrait {

  /**
   * The format mapper service.
   */
  protected ImagemagickFormatMapperInterface $formatMapper;

  /**
   * Returns the format mapper service.
   */
  protected function getFormatMapper(): ImagemagickFormatMapperInterface {
    if (!isset($this->formatMapper)) {
      $this->formatMapper = \Drupal::service(ImagemagickFormatMapperInterface::class);
    }
    return $this->formatMapper;
  }

}
