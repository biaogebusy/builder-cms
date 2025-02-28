<?php

namespace Drupal\conflict\Plugin\Conflict\FieldComparator;

use Drupal\conflict\ConflictTypes;
use Drupal\conflict\FieldComparatorInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Default field comparator plugin implementation covering all fields.
 *
 * @FieldComparator(
 *   id = "conflict_field_comparator_default",
 *   entity_type_id = "*",
 *   bundle = "*",
 *   field_type = "*",
 *   field_name = "*",
 * )
 */
class FieldComparatorDefault extends PluginBase implements FieldComparatorInterface {

  /**
   * {@inheritdoc}
   */
  public function hasChanged(FieldItemListInterface $field_item_list_a, FieldItemListInterface $field_item_list_b, $langcode = NULL, $entity_type_id = NULL, $bundle = NULL, $field_type = NULL, $field_name = NULL) {
    $equals = $field_item_list_a->equals($field_item_list_b);
    return !$equals;
  }

  /**
   * {@inheritdoc}
   */
  public function getConflictType(FieldItemListInterface $local, FieldItemListInterface $server, FieldItemListInterface $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name) {
    // Check for changes between the server and the locally edited version.
    if ($this->hasChanged($server, $local, $langcode, $entity_type_id, $bundle, $field_type, $field_name)) {
      // Check for changes between the server and the locally used original
      // version.
      if ($this->hasChanged($server, $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name)) {
        // Check for changes between the locally edited and locally used
        // original version.
        $conflict_type = $this->hasChanged($local, $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name)
          ? ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE : ConflictTypes::CONFLICT_TYPE_REMOTE;
        return $conflict_type;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // By default, field comparators are available for all fields.
    return TRUE;
  }

}
