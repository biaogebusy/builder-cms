<?php

namespace Drupal\queue_ui;

/**
 * Class QueueUIBase declaration.
 *
 * @package Drupal\queue_ui
 */
abstract class QueueUIBase implements QueueUIInterface {

  /**
   * Retrieve the available operations for the implementing queue class.
   */
  abstract public function getOperations();

  /**
   * Inspect the queue items in a specified queue.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   */
  abstract public function getItems($queueName);

  /**
   * Force the releasing of a queue.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   */
  abstract public function releaseItems($queueName);

  /**
   * View item data for a specified queue item.
   *
   * @param int $item_id
   *   The item id to be viewed.
   */
  abstract public function loadItem($item_id);

  /**
   * Force the releasing of a specified queue item.
   *
   * @param int $item_id
   *   The item id to be released.
   */
  abstract public function releaseItem($item_id);

  /**
   * Force the deletion of a specified queue item.
   *
   * @param int $item_id
   *   The item id to be deleted.
   */
  abstract public function deleteItem($item_id);

}
