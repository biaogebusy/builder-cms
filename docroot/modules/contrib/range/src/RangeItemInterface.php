<?php

namespace Drupal\range;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * An interface for range field types.
 */
interface RangeItemInterface extends FieldItemInterface {

  /**
   * Helper function. Returns Schema API column specification.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return array
   *   Schema API column specification.
   */
  public static function getColumnSpecification(FieldStorageDefinitionInterface $field_definition);

}
