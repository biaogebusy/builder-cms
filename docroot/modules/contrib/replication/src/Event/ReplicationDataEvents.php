<?php

namespace Drupal\replication\Event;

/**
 * Replication events.
 */
final class ReplicationDataEvents {

  /**
   * Allows altering of normalized content data.
   *
   * This event allows modules to perform an action whenever a content entity
   *  is normalized by the ContentEntityNormalizer. The event listener method
   *  receives a \Drupal\replication\Event\ContentDataAlterEvent instance.
   */
  const ALTER_CONTENT_DATA = 'replication.alter.content';

}
