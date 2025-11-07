<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_share_client\Event\RelationshipFieldValueEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Allows entity reference revisions fields to be supported.
 *
 * @package Drupal\entity_share_client
 */
class EntityReferenceRevisionsRelationshipSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[RelationshipFieldValueEvent::EVENT_NAME] = [
      'alterRelationshipValue', 100,
    ];
    return $events;
  }

  /**
   * Set the revision target ID to the last revision of the entity.
   *
   * Last revision of the entity on the client site.
   *
   * @param \Drupal\entity_share_client\Event\RelationshipFieldValueEvent $event
   *   The event containing the field value.
   */
  public function alterRelationshipValue(RelationshipFieldValueEvent $event) {
    $field = $event->getField();
    $field_type = $field->getFieldDefinition()->getType();

    if ($field_type == 'entity_reference_revisions') {
      $field_storage_definition = $field->getFieldDefinition()->getFieldStorageDefinition();
      $entity_type = $field_storage_definition->getSetting('target_type');
      $main_property = $field->getItemDefinition()->getMainPropertyName();
      $field_value = $event->getFieldValue();

      /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
      $referenced_entity = $this->entityTypeManager->getStorage($entity_type)->load($field_value[$main_property]);
      $last_revision_id = $referenced_entity->getRevisionId();

      $field_value['target_revision_id'] = $last_revision_id;
      $event->setFieldValue($field_value);
    }
  }

}
