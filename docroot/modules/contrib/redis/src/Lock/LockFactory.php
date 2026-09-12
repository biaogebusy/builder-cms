<?php

namespace Drupal\redis\Lock;

use Drupal\redis\ClientFactory;

/**
 * Lock backend singleton handling.
 */
class LockFactory {

  /**
   * Creates a redis LockFactory.
   */
  public function __construct(protected ClientFactory $clientFactory) {
  }

  /**
   * Get actual lock backend.
   *
   * @param bool $persistent
   *   (optional) Whether to return a persistent lock implementation or not.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   *   Return lock backend instance.
   */
  public function get($persistent = FALSE) {
    return new RedisLock($this->clientFactory, $persistent);
  }
}
