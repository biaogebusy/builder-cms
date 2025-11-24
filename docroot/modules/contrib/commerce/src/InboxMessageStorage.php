<?php

namespace Drupal\commerce;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;

/**
 * Provides the inbox message storage service.
 */
class InboxMessageStorage implements InboxMessageStorageInterface {

  /**
   * Table name.
   */
  const TABLE_NAME = 'commerce_inbox_message';

  /**
   * Constructs a new InboxMessageStorage object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(protected Connection $connection) {}

  /**
   * {@inheritdoc}
   */
  public function save(InboxMessage $message) {
    $fields = (array) $message;
    $result = $this->connection->merge(self::TABLE_NAME)
      ->fields($fields)
      ->keys([
        'id' => $message->id,
        'state' => 'unread',
      ])
      ->execute();
    Cache::invalidateTags(['commerce_inbox_message']);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $conditions = [], ?int $limit = NULL): array {
    $messages = [];
    $query = $this->connection->select(self::TABLE_NAME, 'm')
      ->fields('m')
      ->orderBy('send_date', 'DESC');

    if ($limit) {
      $query->range(0, $limit);
    }

    foreach ($conditions as $key => $condition) {
      if (is_array($condition)) {
        $query->condition($condition['field'], $condition['value'], $condition['operator'] ?? '=');
      }
      else {
        $query->condition($key, $condition);
      }
    }
    $messages_data = $query->execute()
      ->fetchAll();

    foreach ($messages_data as $message_data) {
      $messages[] = InboxMessage::fromArray((array) $message_data);
    }

    return $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadCount(): int {
    return (int) $this->connection->select(self::TABLE_NAME, 'm')
      ->condition('m.state', 'unread')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getUnDismissedCount(): int {
    return (int) $this->connection->select(self::TABLE_NAME, 'm')
      ->condition('m.state', 'dismissed', '!=')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function setState(string $message_id, string $state): void {
    $this->connection->update(self::TABLE_NAME)
      ->condition('id', $message_id)
      ->fields(['state' => $state])
      ->execute();
    Cache::invalidateTags(['commerce_inbox_message']);
  }

}
