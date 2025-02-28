<?php

namespace Drupal\charts\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides getters and setters for the type.
 */
class TypesInfoEvent extends Event {

  /**
   * The chart types.
   *
   * @var array
   */
  protected $types;

  /**
   * Constructs a new TypesInfoEvent object.
   *
   * @param array $types
   *   The chart type definitions.
   */
  public function __construct(array $types) {
    $this->types = $types;
  }

  /**
   * Gets the condition definitions.
   *
   * @return array
   *   The condition definitions.
   */
  public function getTypes(): array {
    return $this->types;
  }

  /**
   * Sets the condition definitions.
   *
   * @param array $types
   *   The condition definitions.
   *
   * @return $this
   */
  public function setTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  /**
   * Gets a chart type.
   *
   * @param string $type
   *   The chart type name.
   *
   * @return array
   *   The chart type info.
   */
  public function getType($type): array {
    return $this->types[$type] ?? [];
  }

  /**
   * Sets a chart type information.
   *
   * @param string $type
   *   The chart type name.
   * @param array $info
   *   The chart type information settings.
   *
   * @return $this
   */
  public function setType($type, array $info) {
    if ($this->getType($type)) {
      $this->types[$type] = $info;
    }
    return $this;
  }

}
