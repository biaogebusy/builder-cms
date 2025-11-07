<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff entity reference fields.
 *
 * @DiffGenerator(
 *   id = "dynamic_entity_reference_field_diff_parser",
 *   label = @Translation("Dynamic Entity Reference Field Parser"),
 *   field_types = {
 *     "dynamic_entity_reference",
 *   },
 * )
 */
class DynamicEntityReferenceFieldDiffParser extends EntityReferenceFieldDiffParser {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];

    // Case of local entity:
    // Every item from $field_items is of type FieldItemInterface.
    if (!$this->getRemote()) {
      foreach ($field_items as $field_key => $field_item) {
        if (!$field_item->isEmpty()) {
          if ($field_item->entity) {
            $entity = $field_item->entity;
            $entity_type_id = $entity->getEntityTypeId();
            $result[$field_key] = $entity_type_id . ': ' . $entity->uuid();
          }
        }
      }
    }

    // Case of remote entity.
    elseif (!empty($remote_field_data['data'])) {
      foreach ($remote_field_data['data'] as $field_key => $remote_item_data) {
        list($entity_type_id,) = explode('--', $remote_item_data['type']);
        $result[$field_key] = $entity_type_id . ': ' . $remote_item_data['id'];
      }
    }

    return $result;
  }

}
