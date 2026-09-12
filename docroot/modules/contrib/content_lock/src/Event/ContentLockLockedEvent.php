<?php

namespace Drupal\content_lock\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Respond to a lock being successfully set.
 */
class ContentLockLockedEvent extends Event {

  /**
   * The event name.
   *
   * @deprecated in content_lock:3.0.0 and is removed from content_lock:4.0.0.
   *   Use the class name instead.
   *
   * @see https://www.drupal.org/project/content_lock/issues/3598236
   */
  const EVENT_NAME = self::class;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    public string $entityId,
    public string $langcode,
    public string $formOp,
    public int $uid,
    public string $entityType,
  ) {
  }

}
