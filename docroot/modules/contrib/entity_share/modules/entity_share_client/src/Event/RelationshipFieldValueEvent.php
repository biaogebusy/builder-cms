<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines a RelationshipFieldValueEvent event.
 */
class RelationshipFieldValueEvent extends Event {

  const EVENT_NAME = 'entity_share_client.relationship_field_value';

  /**
   * A FieldItemList object.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $field;

  /**
   * An array of the field value to alter.
   *
   * @var array
   */
  protected $fieldValue;

  /**
   * Constructs a new RelationshipFieldValueEvent.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   A FieldItemList object.
   * @param array $field_value
   *   An array of the field value to alter.
   */
  public function __construct(FieldItemListInterface $field, array $field_value) {
    $this->field = $field;
    $this->fieldValue = $field_value;
  }

  /**
   * Returns the FieldItemList object.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Returns the FieldItemList object.
   */
  public function getField() {
    return $this->field;
  }

  /**
   * Returns the field value.
   *
   * @return array
   *   Returns the field value.
   */
  public function getFieldValue() {
    return $this->fieldValue;
  }

  /**
   * Set the field value.
   *
   * @param array $field_value
   *   The field value.
   */
  public function setFieldValue(array $field_value) {
    $this->fieldValue = $field_value;
  }

}
