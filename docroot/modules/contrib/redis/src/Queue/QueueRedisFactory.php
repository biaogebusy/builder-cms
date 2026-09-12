<?php

namespace Drupal\redis\Queue;

use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\redis\ClientFactory;

/**
 * Defines the queue factory for the Redis backend.
 */
class QueueRedisFactory implements QueueFactoryInterface {

  public function __construct(protected ClientFactory $clientFactory, protected Settings $settings) {
  }

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    $settings = $this->settings->get('redis_queue_' . $name, ['reserve_timeout' => NULL]);
    return new RedisQueue($name, $settings, $this->clientFactory->getClient());
  }

}
