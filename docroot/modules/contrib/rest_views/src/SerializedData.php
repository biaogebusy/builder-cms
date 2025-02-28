<?php

namespace Drupal\rest_views;

/**
 * Wrapper for passing serialized data through render arrays.
 *
 * @see \Drupal\rest_views\Normalizer\DataNormalizer
 */
class SerializedData {

  /**
   * The wrapped data.
   *
   * @var mixed
   */
  protected $data;

  /**
   * SerializedData constructor.
   *
   * @param mixed $data
   *   The wrapped data.
   */
  public function __construct($data) {
    $this->data = $data;
  }

  /**
   * Create a serialized data object.
   *
   * @param mixed $data
   *   The wrapped data.
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
   * Extract the wrapped data.
   *
   * @return mixed
   *   The wrapped data.
   */
  public function getData() {
    return $this->data;
  }

}
