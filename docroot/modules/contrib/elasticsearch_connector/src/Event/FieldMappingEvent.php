<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\Item\FieldInterface;

/**
 * Event triggered when a field is mapped.
 */
class FieldMappingEvent extends Event {

  /**
   * Creates a new event.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   * @param array $param
   *   The mapping param.
   */
  public function __construct(
    protected FieldInterface $field,
    protected array $param,
  ) {
  }

  /**
   * Gets the field.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   The field.
   */
  public function getField(): FieldInterface {
    return $this->field;
  }

  /**
   * Gets the param.
   *
   * @return array
   *   The param.
   */
  public function getParam(): array {
    return $this->param;
  }

  /**
   * Sets the param.
   *
   * @param array $param
   *   The param.
   *
   * @return $this
   *   The current object.
   */
  public function setParam(array $param): FieldMappingEvent {
    $this->param = $param;
    return $this;
  }

}
