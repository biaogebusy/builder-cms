<?php

namespace Drupal\redis\Flood;

use Drupal\redis\ClientFactory;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Flood backend singleton handling.
 */
class FloodFactory {

  public function __construct(protected ClientFactory $clientFactory, protected RequestStack $requestStack) {
  }

  /**
   * Get actual flood backend.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   *   Return flood instance.
   */
  public function get() {
    return new RedisFloodBackend($this->clientFactory, $this->requestStack);
  }
}
