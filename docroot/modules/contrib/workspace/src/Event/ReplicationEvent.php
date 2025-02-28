<?php

namespace Drupal\workspace\Event;

use Drupal\workspace\Entity\Replication;
use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a replication for event listeners.
 */
class ReplicationEvent extends Event {

  /**
   * The replication object.
   *
   * @var \Drupal\workspace\Entity\Replication
   */
  protected $replication;

  /**
   * ReplicationEvent constructor.
   *
   * @param \Drupal\workspace\Entity\Replication $replication
   *   The Replication object.
   */
  public function __construct(Replication $replication) {
    $this->replication = $replication;
  }

  /**
   * Gets the replication.
   *
   * @return \Drupal\workspace\Entity\Replication
   *   Return replication object.
   */
  public function getReplication() {
    return $this->replication;
  }

}
