<?php

namespace Drupal\conflict\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class EntityConflictDiscoveryEvent extends Event {

  /**
   * The local entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $localEntity;

  /**
   * The remote entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $remoteEntity;

  /**
   * The base entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $baseEntity;

  /**
   * A context parameter bag.
   *
   * It might contain configuration and/or form data during concurrent editing.
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  protected $context;

  /**
   * An array containing the conflicting properties.
   *
   * The keys are the property names and the values the respective conflict
   * type.
   *
   * @var array
   */
  protected $conflicts = [];

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
   * @param \Symfony\Component\HttpFoundation\ParameterBag $context
   *   (optional) The context parameter bag.
   */
  public function __construct(EntityInterface $local, EntityInterface $remote, EntityInterface $base, ParameterBag $context = NULL) {
    $this->localEntity = $local;
    $this->remoteEntity = $remote;
    $this->baseEntity = $base;
    $this->context = $context ?? new ParameterBag();
  }

  /**
   * Return the local entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getLocalEntity() : EntityInterface {
    return $this->localEntity;
  }

  /**
   * Returns the remote entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getRemoteEntity() : EntityInterface {
    return $this->remoteEntity;
  }

  /**
   * Returns the base entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getBaseEntity() : EntityInterface {
    return $this->baseEntity;
  }

  /**
   * Adds a conflicting property.
   *
   * @param string $property
   *   The property name.
   * @param string $type
   *   The conflict type.
   */
  public function addConflict($property, $type) {
    $this->conflicts[$property] = $type;
  }

  /**
   * Returns the conflicts.
   *
   * @return array
   */
  public function getConflicts() : array {
    return $this->conflicts;
  }

  /**
   * Returns a parameter value from the context bag.
   *
   * @param $parameter
   *   The parameter key.
   * @param mixed $default
   *   The default value if the parameter key does not exist
   *
   * @return mixed|null
   */
  public function getContextParameter($parameter, $default = NULL) {
    return $this->context->get($parameter, $default);
  }

}
