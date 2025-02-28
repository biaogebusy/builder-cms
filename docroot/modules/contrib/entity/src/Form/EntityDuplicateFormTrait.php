<?php

namespace Drupal\entity\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity\Event\EntityDuplicateEvent;
use Drupal\entity\Event\EntityEvents;

/**
 * Allows forms to implement EntityDuplicateFormInterface.
 *
 * Forms are expected to call $this->postSave() after the entity is saved.
 * This works around core issue #3040556.
 */
trait EntityDuplicateFormTrait {

  /**
   * The source entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $sourceEntity;

  /**
   * {@inheritdoc}
   */
  public function getSourceEntity() {
    return $this->sourceEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceEntity(EntityInterface $source_entity) {
    $this->sourceEntity = $source_entity;
    return $this;
  }

  /**
   * Invokes entity duplicate hooks after the entity has been duplicated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The saved entity.
   * @param string $operation
   *   The form operation.
   */
  protected function postSave(EntityInterface $entity, $operation) {
    if ($operation == 'duplicate') {
      // An event is used instead of a hook to prevent a conflict with core
      // once hook_entity_duplicate() is introduced there.
      $event = new EntityDuplicateEvent($entity, $this->sourceEntity);
      /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher */
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->dispatch($event, EntityEvents::ENTITY_DUPLICATE);
    }
  }

}
