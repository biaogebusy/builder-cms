<?php

namespace Drupal\conflict;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;

interface FieldComparatorInterface {

  /**
   * The identifier to be used by the plugin annotation and hasChanged().
   *
   * It specifies that a field comparator applies to all values of the key for
   * which it has been set as a value.
   */
  const APPLIES_TO_ALL = '*';

  /**
   * Checks whether the field items have changed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field_item_list_a
   *   A field item list.
   * @param \Drupal\Core\Field\FieldItemListInterface $field_item_list_b
   *   Another field item list.
   * @param string $langcode
   *   (optional) The language code of the entity translation being checked.
   * @param  string $entity_type_id
   *   (optional) The entity type ID.
   * @param string $bundle
   *   (optional) The entity bundle.
   * @param string $field_type
   *   (optional) The field type.
   * @param string $field_name
   *   (optional) The field name.
   *
   * @return bool|null
   *   TRUE, if both field item lists are equal, FALSE otherwise. NULL can be
   *   returned if this comparator cannot make a decision and the next one
   *   should be called.
   */
  public function hasChanged(FieldItemListInterface $field_item_list_a, FieldItemListInterface $field_item_list_b, $langcode = NULL, $entity_type_id = NULL, $bundle = NULL, $field_type = NULL, $field_name = NULL);

  /**
   * Returns the conflict type for a field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $local
   *   The local field item list to compare.
   * @param \Drupal\Core\Field\FieldItemListInterface $server
   *   The server field item list to compare.
   * @param \Drupal\Core\Field\FieldItemListInterface $original
   *   The original field item list, from which local and the server emerged.
   * @param string $langcode
   *   The language code of the entity translation being checked.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The entity bundle ID.
   * @param string $field_type
   *   The field type.
   * @param string $field_name
   *   The field name.
   *
   * @return string|null
   *   The conflict type or NULL if none.
   *
   * @throws \Exception
   *   An exception will be thrown if for some reason even the default field
   *   comparator has not been added to the field comparators list.
   */
  public function getConflictType(FieldItemListInterface $local, FieldItemListInterface $server, FieldItemListInterface $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name);

  /**
   * Returns if the field comparator can be used for the provided field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition that should be checked.
   *
   * @return bool
   *   TRUE if the field comparator can be used, FALSE otherwise.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition);

}
