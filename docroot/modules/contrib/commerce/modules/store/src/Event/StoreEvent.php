<?php

namespace Drupal\commerce_store\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Defines the store event.
 *
 * @see \Drupal\commerce_store\Event\StoreEvents
 */
class StoreEvent extends EventBase {

  /**
   * The store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * Constructs a new StoreEvent.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   */
  public function __construct(StoreInterface $store) {
    $this->store = $store;
  }

  /**
   * Gets the store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The store.
   */
  public function getStore() {
    return $this->store;
  }

}
