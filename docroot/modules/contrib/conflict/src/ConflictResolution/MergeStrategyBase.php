<?php

namespace Drupal\conflict\ConflictResolution;

use Drupal\conflict\Event\EntityConflictEvents;
use Drupal\conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\ContentEntityInterface;

abstract class MergeStrategyBase implements MergeStrategyInterface {

  /**
   * Discovers conflicts on entities.
   *
   * @param \Drupal\conflict\Event\EntityConflictResolutionEvent
   *   The entity conflict resolution event.
   */
  public function resolveConflicts(EntityConflictResolutionEvent $event) {
    if ($this->isEnabled($event)) {
      $local_entity = $event->getLocalEntity();
      if ($local_entity instanceof ContentEntityInterface) {
        $this->resolveConflictsContentEntity($event);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(EntityConflictResolutionEvent $event) : bool {
    $disabled_merge_strategies = $event->getContextParameter('merge_strategy.disabled', []);
    return !in_array($this->getMergeStrategyId(), $disabled_merge_strategies, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityConflictEvents::ENTITY_CONFLICT_RESOLVE][] = ['resolveConflicts'];
    return $events;
  }

}
