<?php

namespace Drupal\redis\Client;


use Drupal\redis\ClientInterface;

/**
 * Redis client factory interface.
 */
interface RedisClientFactoryInterface {

  /**
   * Returns whether this client is available
   *
   * @return bool
   *   Whether the client is available.
   */
  public function isAvailable(): bool;

  /**
   * Returns the client.
   *
   * @param array $settings
   *   The connection settings.
   *
   * @return \Drupal\redis\ClientInterface
   *   The client.
   */
  public function getClient(array $settings): ClientInterface;

  /**
   * Get underlying library name used.
   *
   * This can be useful for contribution code that may work with only some of
   * the provided clients.
   *
   * @return string
   */
  public function getName(): string;

}
