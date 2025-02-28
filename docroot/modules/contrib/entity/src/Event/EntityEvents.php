<?php

namespace Drupal\entity\Event;

/**
 * Defines events for the Entity module.
 */
final class EntityEvents {

  /**
   * Name of the event fired after saving a duplicated entity.
   *
   * @Event
   *
   * @see \Drupal\entity\Event\EntityDuplicateEvent
   */
  const ENTITY_DUPLICATE = 'entity.duplicate';

}
