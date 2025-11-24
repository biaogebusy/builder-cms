<?php

namespace Drupal\rest_views;

use Drupal\Component\Render\MarkupInterface;

/**
 * Wrapper for passing serialized data through render arrays.
 *
 * This class implements MarkupInterface in order to pass through the render
 * system without being turned into a string. It is actually intended to be
 * processed by the normalization system.
 *
 * @see \Drupal\rest_views\Normalizer\DataNormalizer
 */
class SerializedData implements MarkupInterface {

  /**
   * The wrapped data.
   *
   * @var mixed
   */
  protected mixed $data;

  /**
   * SerializedData constructor.
   *
   * @param mixed $data
   *   The wrapped data.
   */
  public function __construct(mixed $data) {
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
  public static function create(mixed $data): static {
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
  public function getData(): mixed {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): mixed {
    return $this->getData();
  }

}
