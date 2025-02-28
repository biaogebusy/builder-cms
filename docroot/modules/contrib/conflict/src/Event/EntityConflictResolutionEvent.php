<?php

namespace Drupal\conflict\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class EntityConflictResolutionEvent extends EntityConflictDiscoveryEvent {

  /**
   * The result entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $resultEntity;

  /**
   * Constructs a conflict discovery event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $local
   *   The local part of the comparision - for example the entity built of the
   *   user input on an entity form submission. This is basically the active
   *   entity object.
   * @param \Drupal\Core\Entity\EntityInterface $remote
   *   The remote part of the comparision - for example the current version of
   *   the entity from the storage.
   * @param \Drupal\Core\Entity\EntityInterface $base
   *   The initial entity version in concurrent editing or the lowest common
   *   ancestor in a revision tree scenario.
   * @param \Drupal\Core\Entity\EntityInterface $result
   *   The result entity, on which to apply the result. Usually this will be the
   *   active entity object - the local entity.
   * @param array $conflicts
   *   The conflicts.
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   */
  public function __construct(EntityInterface $local, EntityInterface $remote, EntityInterface $base, EntityInterface $result, array $conflicts, ParameterBag $context = NULL) {
    parent::__construct($local, $remote, $base, $context);
    $this->conflicts = $conflicts;
    $this->resultEntity = $result;
  }

  /**
   * Creates a conflict resolution event object from a conflict discovery event.
   *
   * @param \Drupal\conflict\Event\EntityConflictDiscoveryEvent
   *   The conflict discovery event.
   * @param \Drupal\Core\Entity\EntityInterface $result
   *   The result entity, on which to apply the result.
   *
   * @return static
   */
  public static function createFromConflictDiscoveryEvent(EntityConflictDiscoveryEvent $event, EntityInterface $result) {
    return new static($event->LocalEntity, $event->RemoteEntity, $event->baseEntity, $result, $event->conflicts, $event->context);
  }

  /**
   * Returns the result entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getResultEntity() : EntityInterface {
    return $this->resultEntity;
  }

  /**
   * Removes a conflict.
   *
   * This method should called when a conflict has been resolved or the property
   * value on the resulting entity satisfies the merge strategy.
   *
   * @param string $property
   *   The conflicting property.
   */
  public function removeConflict($property) {
    unset($this->conflicts[$property]);
  }

}
