<?php

namespace Drupal\rest_views;

/**
 * Wrapper for renderable data that will be rendered during normalization.
 *
 * @package Drupal\rest_views
 */
class RenderableData {

  /**
   * The render array.
   *
   * @var array
   */
  protected $data;

  /**
   * RenderableData constructor.
   *
   * @param array $data
   *   The render array.
   */
  public function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * Create a renderable data object.
   *
   * @param array|static $data
   *   The render array.
   *
   * @return static
   */
  public static function create($data): self {
    if ($data instanceof static) {
      return $data;
    }
    return new static($data);
  }

  /**
   * Convert renderable object to a string.
   *
   * This function needs to return a non-empty string in order to be processed
   * correctly by Drupal's rendering system.
   *
   * @return string
   *   A placeholder string representation.
   */
  public function __toString(): string {
    // This must not be empty.
    return '[...]';
  }

  /**
   * Extract the render array.
   *
   * @return array
   *   The render array.
   */
  public function getData(): array {
    return $this->data;
  }

}
