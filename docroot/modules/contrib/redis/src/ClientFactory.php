<?php

namespace Drupal\redis;

use Drupal\Core\Site\Settings;
use Drupal\redis\Client\PhpRedisFactory;
use Drupal\redis\Client\PredisFactory;
use Drupal\redis\Client\RedisClientFactoryInterface;
use Drupal\redis\Client\RelayFactory;

/**
 * Common code and client singleton, for all Redis clients.
 */
class ClientFactory {
  /**
   * Redis default host.
   */
  const REDIS_DEFAULT_HOST = "127.0.0.1";

  /**
   * Redis default port.
   */
  const REDIS_DEFAULT_PORT = 6379;

  /**
   * Redis default database: will select none (Database 0).
   */
  const REDIS_DEFAULT_BASE = NULL;

  /**
   * Redis default password: will not authenticate.
   */
  const REDIS_DEFAULT_PASSWORD = NULL;

  /**
   * The client adapter.
   *
   * @var \Drupal\redis\ClientInterface|null
   */
  protected ?ClientInterface $client = NULL;

  /**
   * @var \Drupal\redis\Client\RedisClientFactoryInterface[]
   */
  protected array $factories = [];

  /**
   * Whether the factory has an active client.
   *
   * @return bool
   *   True if there is an instantiated client, false if not.
   */
  public function hasClient(): bool {
    return $this->client instanceof ClientInterface;
  }

  /**
   * Get underlying library name.
   *
   * @return string|null
   */
  public function getClientName(): ?string {
    return $this->client?->getName();
  }

  /**
   * Add a client factory.
   *
   * @param \Drupal\redis\Client\RedisClientFactoryInterface $client_factory
   *   The client factory.
   */
  public function addFactory(RedisClientFactoryInterface $client_factory): void {
    $this->factories[$client_factory->getName()] = $client_factory;
  }

  /**
   * Get client singleton.
   */
  public function getClient(): ClientInterface {
    if (!isset($this->client)) {
      $settings = Settings::get('redis.connection', []);
      $settings += [
        'host' => self::REDIS_DEFAULT_HOST,
        'port' => self::REDIS_DEFAULT_PORT,
        'base' => self::REDIS_DEFAULT_BASE,
        'password' => self::REDIS_DEFAULT_PASSWORD,
        'persistent' => FALSE,
      ];

      // If using replication, lets create the client appropriately.
      if (isset($settings['replication']) && $settings['replication'] === TRUE) {
        foreach ($settings['replication.host'] as $key => $replicationHost) {
          if (!isset($replicationHost['port'])) {
            $settings['replication.host'][$key]['port'] = self::REDIS_DEFAULT_PORT;
          }
        }
      }

      // For early bootstrap container, the client factories aren't initialized
      // yet.
      if (empty($this->factories)) {
        foreach ([PhpRedisFactory::class, PredisFactory::class, RelayFactory::class] as $client_factory_class) {
          $client_factory = new $client_factory_class();
          $this->factories[$client_factory->getName()] = $client_factory;
        }
      }

      // If a specific client interface was requested, only use that.
      if (isset($settings['interface'])) {
        if (!isset($this->factories[$settings['interface']])) {
          throw new \InvalidArgumentException('Invalid interface ' . $settings['interface']);
        }
        $this->client = $this->factories[$settings['interface']]->getClient($settings);
      }
      else {
        // Fall back to returning the first-available client based on priority.
        foreach ($this->factories as $client_factory) {
          if ($client_factory->isAvailable()) {
            $this->client = $client_factory->getClient($settings);
            break;
          }
        }
      }
    }

    return $this->client;
  }

}

