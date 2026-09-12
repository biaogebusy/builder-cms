<?php

namespace Drupal\eck;

use Drupal\Core\Render\RenderableInterface;

/**
 * Defines a wrapper for deprecating access to an array with a warning.
 *
 * @package Drupal\eck
 */
class ArrayDeprecationWrapper implements \ArrayAccess, RenderableInterface {

  /**
   * The deprecated array.
   *
   * @var array
   */
  private $wrappedArray;

  /**
   * The deprecation warning to set.
   *
   * @var string
   */
  private $deprecationWarning;

  /**
   * ArrayDeprecationWrapper constructor.
   *
   * @param array $wrappedArray
   *   The array being deprecated.
   * @param string $deprecationWarning
   *   The warning that should be raised when it is accessed.
   */
  public function __construct(array &$wrappedArray, $deprecationWarning) {
    $this->wrappedArray = $wrappedArray;
    $this->deprecationWarning = $deprecationWarning;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetExists($offset) {
    @trigger_error($this->deprecationWarning, E_USER_DEPRECATED);
    return isset($this->wrappedArray[$offset]);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetGet($offset) {
    @trigger_error($this->deprecationWarning, E_USER_DEPRECATED);
    return $this->wrappedArray[$offset];
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetSet($offset, $value) {
    @trigger_error($this->deprecationWarning, E_USER_DEPRECATED);
    $this->wrappedArray[$offset] = $value;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetUnset($offset) {
    @trigger_error($this->deprecationWarning, E_USER_DEPRECATED);
    unset($this->wrappedArray[$offset]);
  }

  /**
   * Returns a render array representation of the object.
   *
   * @return mixed[]
   *   A render array.
   */
  public function toRenderable() {
    @trigger_error($this->deprecationWarning, E_USER_DEPRECATED);
    return $this->wrappedArray;
  }

}
