<?php

namespace Drupal\commerce;

/**
 * Provides interface for the inbox message storage service.
 */
interface InboxMessageStorageInterface {

  /**
   * Saves an inbox message to database.
   *
   * @param \Drupal\commerce\InboxMessage $message
   *   The message object.
   */
  public function save(InboxMessage $message);

  /**
   * Loads the inbox messages.
   *
   * @param array $conditions
   *   An array of conditions.
   * @param int|null $limit
   *   (NULL) Limit the number of results returned.
   *
   * @return \Drupal\commerce\InboxMessage[]
   *   The messages.
   */
  public function loadMultiple(array $conditions = [], ?int $limit = NULL): array;

  /**
   * Gets count of the unread messages.
   *
   * @return int
   *   The count.
   */
  public function getUnreadCount(): int;

  /**
   * Gets count of the un-dismissed messages.
   *
   * @return int
   *   The count.
   */
  public function getUnDismissedCount(): int;

  /**
   * Sets state for the message.
   *
   * @param string $message_id
   *   The message ID.
   * @param string $state
   *   The state.
   */
  public function setState(string $message_id, string $state): void;

}
