<?php

namespace Drupal\entity\Event;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Defines the entity duplicate event.
 *
 * @see \Drupal\entity\Event\EntityEvents
 */
class EntityDuplicateEvent extends Event {

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The source entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $sourceEntity;

  /**
   * Constructs a new EntityDuplicateEvent object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   */
  public function __construct(EntityInterface $entity, EntityInterface $source_entity) {
    $this->entity = $entity;
    $this->sourceEntity = $source_entity;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Gets the source entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The source entity.
   */
  public function getSourceEntity() {
    return $this->sourceEntity;
  }

}
