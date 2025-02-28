<?php

namespace Drupal\conflict\ConflictDiscovery;

use Drupal\conflict\Event\EntityConflictDiscoveryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface ConflictDiscoveryInterface extends EventSubscriberInterface {

  /**
   * Discovers conflicts on content entities.
   *
   * @param \Drupal\conflict\Event\EntityConflictDiscoveryEvent
   *   The entity conflict discovery event.
   */
  public function discoverConflictsContentEntity(EntityConflictDiscoveryEvent $event);

}
