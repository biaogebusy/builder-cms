<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

/**
 * Trait for ImageMagick image toolkit operations.
 */
trait ImagemagickOperationTrait {

  /**
   * The format mapper service.
   *
   * @var \Drupal\imagemagick\ImagemagickFormatMapperInterface
   */
  protected $formatMapper;

  /**
   * Returns the format mapper service.
   *
   * @return \Drupal\imagemagick\ImagemagickFormatMapperInterface
   *   The format mapper service.
   */
  protected function getFormatMapper() {
    if (!$this->formatMapper) {
      $this->formatMapper = \Drupal::service('imagemagick.format_mapper');
    }
    return $this->formatMapper;
  }

}
