<?php

namespace Drupal\redis\Queue;

/**
 * Defines the queue factory for the Redis backend.
 */
class ReliableQueueRedisFactory extends QueueRedisFactory {

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    $settings = $this->settings->get('redis_queue_' . $name, ['reserve_timeout' => NULL]);
    return new ReliableRedisQueue($name, $settings, $this->clientFactory->getClient());
  }

}
