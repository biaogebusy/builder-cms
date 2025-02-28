<?php

namespace Drupal\conflict\Event;

/**
 * Defines events for entity conflict discovery and conflict resolution.
 *
 * @see \Drupal\conflict\Event\EntityConflictDiscoveryEvent
 * @see \Drupal\conflict\Event\EntityConflictResolutionEvent
 */
final class EntityConflictEvents {

  /**
   * The name of the event fired when searching for conflicts.
   *
   * @Event
   */
  const ENTITY_CONFLICT_DISCOVERY = 'entity_conflict.discovery';

  /**
   * The name of the event fired when resolving conflicts.
   *
   * @Event
   */
  const ENTITY_CONFLICT_RESOLVE = 'entity_conflict.resolve';

}
