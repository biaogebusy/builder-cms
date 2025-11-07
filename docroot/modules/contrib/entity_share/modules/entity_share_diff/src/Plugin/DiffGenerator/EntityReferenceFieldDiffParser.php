<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff entity reference fields.
 *
 * @DiffGenerator(
 *   id = "entity_reference_field_diff_parser",
 *   label = @Translation("Entity Reference Field Parser"),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class EntityReferenceFieldDiffParser extends DiffGeneratorPluginBase {

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
          // Compare entity label.
          if ($field_item->entity) {
            $entity = $field_item->entity;
            // Should we go into recursion and embed the referenced entity?
            // If the entity has already been processed, don't embed,
            // to avoid infinite loop.
            // If the referenced entity type is not Paragraph or Media,
            // don't embed.
            if ($this->entityParser->referenceEmbeddable($entity->getEntityTypeId()) &&
                $this->entityParser->validateNeedToProcess($entity->uuid(), FALSE)) {
              $result[$field_key] = $this->entityParser->prepareLocalEntity($entity);
            }
            // If we are not embedding, just show the referenced entity's UUID.
            else {
              $result[$field_key] = $entity->label() . ' (' . $entity->uuid() . ')';
            }
          }
        }
      }
    }

    // Case of remote entity.
    elseif (!empty($remote_field_data['data'])) {
      $data = [];
      $detailed_response = $this->remoteManager->jsonApiRequest($this->getRemote(), 'GET', $remote_field_data['links']['related']['href']);

      if (!is_null($detailed_response)) {
        $entities_json = Json::decode((string) $detailed_response->getBody());
        if (!empty($entities_json['data'])) {
          $data = EntityShareUtility::prepareData($entities_json['data']);
        }
      }

      foreach ($remote_field_data['data'] as $field_key => $remote_item_data) {
        $uuid = $data[$field_key]['id'];
        list($referenced_entity_type,) = explode('--', $remote_item_data['type']);
        if ($this->entityParser->referenceEmbeddable($referenced_entity_type) &&
            $this->entityParser->validateNeedToProcess($uuid, TRUE)) {
          $result[$field_key] = $this->entityParser->prepareRemoteEntity($data[$field_key], $this->getRemote());
        }
        elseif (isset($data[$field_key])) {
          $public_title_key = $this->entityParser->getPublicFieldName('title', $data[$field_key]);
          $title = $data[$field_key]['attributes'][$public_title_key] ?? '';
          $uuid = $remote_item_data['id'];
          $result[$field_key] = "$title ($uuid)";
        }
      }
    }

    return $result;
  }

}
