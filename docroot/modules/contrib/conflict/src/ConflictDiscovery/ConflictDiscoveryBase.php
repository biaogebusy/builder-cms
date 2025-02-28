<?php

namespace Drupal\conflict\ConflictDiscovery;

use Drupal\conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\conflict\Event\EntityConflictEvents;
use Drupal\Core\Entity\ContentEntityInterface;

abstract class ConflictDiscoveryBase implements ConflictDiscoveryInterface {

  /**
   * Discovers conflicts on entities.
   *
   * @param \Drupal\conflict\Event\EntityConflictDiscoveryEvent
   *   The entity conflict discovery event.
   */
  public function discoverConflicts(EntityConflictDiscoveryEvent $event) {
    $local_entity = $event->getLocalEntity();
    if ($local_entity instanceof ContentEntityInterface) {
      $this->discoverConflictsContentEntity($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityConflictEvents::ENTITY_CONFLICT_DISCOVERY][] = ['discoverConflicts'];
    return $events;
  }

}
