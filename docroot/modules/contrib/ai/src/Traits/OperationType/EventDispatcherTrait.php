<?php

namespace Drupal\ai\Traits\OperationType;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Event dispatcher trait for operation types.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait EventDispatcherTrait {

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Get the event dispatcher service.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher service.
   */
  public function getEventDispatcher(): EventDispatcherInterface {
    // Check if the event dispatcher is set.
    if ($this->eventDispatcher) {
      return $this->eventDispatcher;
    }
    // If not set, return the default event dispatcher service.
    return \Drupal::service('event_dispatcher');
  }

}
