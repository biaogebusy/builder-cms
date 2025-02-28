<?php

namespace Drupal\conflict\ConflictDiscovery;

use Drupal\conflict\ConflictTypes;
use Drupal\conflict\Event\EntityConflictDiscoveryEvent;

class DefaultConflictDiscovery extends ConflictDiscoveryBase {

  /**
   * {@inheritdoc}
   */
  public function discoverConflictsContentEntity(EntityConflictDiscoveryEvent $event) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $local_entity */
    $local_entity = $event->getLocalEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $remote_entity */
    $remote_entity = $event->getRemoteEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $base_entity */
    $base_entity = $event->getBaseEntity();

    $langcode = $local_entity->language()->getId();
    $remote_entity = $remote_entity->getTranslation($langcode);
    $base_entity = $base_entity->getTranslation($langcode);

    // The revision metadata fields are updated constantly and they will always
    // cause conflicts, therefore we skip them here.
    // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
    $skip_fields = array_flip($local_entity->getEntityType()->getRevisionMetadataKeys());

    // Iterate over the entity fields to find conflicting properties.
    foreach ($local_entity->getFields() as $field_name => $field_items_local) {
      if (isset($skip_fields[$field_name])) {
        continue;
      }

      $field_definition = $field_items_local->getFieldDefinition();
      // There could be no conflicts on read only fields.
      if ($field_definition->isReadOnly()) {
        continue;
      }

      $field_items_remote = $remote_entity->get($field_name);
      $field_items_base = $base_entity->get($field_name);

      // Check for changes between the local and the remote entity versions.
      if (!$field_items_local->equals($field_items_remote)) {
        // Check for changes between the remote and the base entity versions.
        if (!$field_items_remote->equals($field_items_base)) {
          // Check for changes between the local and the base entity versions.
          $conflict_type = $field_items_local->equals($field_items_base) ? ConflictTypes::CONFLICT_TYPE_REMOTE : ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE;
          $event->addConflict($field_name, $conflict_type);
        }
      }
    }
  }

}
