<?php

namespace Drupal\conflict\ConflictResolver;

use Drupal\conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\conflict\Event\EntityConflictEvents;
use Drupal\conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Manages conflict resolving.
 */
class ConflictResolverManager implements ConflictResolverManagerInterface {

  /**
   * The event dispatcher used to notify subscribers.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new Conflict Resolver Manager.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher used to notify subscribers of config import events.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflicts(EntityInterface $local, EntityInterface $remote, EntityInterface $base, EntityInterface $result = NULL, ParameterBag $context = NULL, array $conflicts = NULL) : array {
    // If none conflicts are given, then explicitly discover the conflicts.
    $conflicts = $conflicts ?? $this->getConflicts($local, $remote, $base, $context);

    if ($conflicts) {
      $result = $result ?? $local;
      $event = new EntityConflictResolutionEvent($local, $remote, $base, $result, $conflicts, $context);
      // Fire an event to allow listeners to automatically resolve conflicts.
      $this->eventDispatcher->dispatch($event, EntityConflictEvents::ENTITY_CONFLICT_RESOLVE);
      $conflicts = $event->getConflicts();
    }

    return $conflicts;
  }

  /**
   * {@inheritdoc}
   */
  public function getConflicts(EntityInterface $local, EntityInterface $remote, EntityInterface $base, ParameterBag $context = NULL) : array {
    $event = new EntityConflictDiscoveryEvent($local, $remote, $base, $context);
    // Fire an event to allow listeners to build a list of conflicting
    // properties.
    $this->eventDispatcher->dispatch($event, EntityConflictEvents::ENTITY_CONFLICT_DISCOVERY);

    return $event->getConflicts();
  }

}
