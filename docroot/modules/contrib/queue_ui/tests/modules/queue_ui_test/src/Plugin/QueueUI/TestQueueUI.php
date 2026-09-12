<?php

namespace Drupal\queue_ui_test\Plugin\QueueUI;

use Drupal\queue_ui\Attribute\QueueUI;
use Drupal\queue_ui\QueueUIBase;
use Drupal\queue_ui_test\Queue\TestQueue;

/**
 * Test QueueUI plugin implementation.
 */
#[QueueUI(
  id: 'test_queue_ui',
  class_name: TestQueue::class,
)]
class TestQueueUI extends QueueUIBase {

  /**
   * {@inheritdoc}
   */
  public function getOperations() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getItems($queueName) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItems($queueName) {}

  /**
   * {@inheritdoc}
   */
  public function loadItem($item_id) {}

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item_id) {}

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item_id) {}

}
