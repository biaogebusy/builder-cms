<?php

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use Predis\Pipeline\Pipeline;
use Predis\Response\Status;

/**
 * Predis client specific implementation.
 */
class Predis implements ClientInterface {

  protected ?Pipeline $pipeline = NULL;

  public function __construct(protected Client $redis) {}

  /**
   * {@inheritdoc}
   */
  public function pipeline(): void {
    $this->pipeline = $this->redis->pipeline();
  }

  /**
   * {@inheritdoc}
   */
  public function exec(): ?array {
    if ($this->pipeline) {
      $return = $this->pipeline->execute();
      $this->pipeline = NULL;
      return $return;
    }
    else {
      return $this->activeClient()->exec();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lrem(string $key, string $value, int $count): void {
    $this->activeClient()->lrem($key, $count, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $key, mixed $value, mixed $options = null): bool {
    if (is_array($options)) {
      $args = [$key, $value];
      foreach ($options as $option_key => $option_value) {
        if (is_string($option_key)) {
          $args[] = $option_key;
        }
        $args[] = $option_value;
      }
    }
    else {
      $args = func_get_args();
    }
    return (bool) $this->activeClient()->set(...$args)?->getPayload();
  }

  /**
   * {@inheritdoc}
   */
  public function scan(string $match, int $count = 1000) {
    yield from new Keyspace($this->redis, $match, $count);
  }

  /**
   * {@inheritdoc}
   */
  public function info(): array {
    $info = $this->activeClient()->info();

    $info['redis_version'] = $info['redis_version'] ?? $info['Server']['redis_version'] ?? NULL;
    $info['valkey_version'] = $info['valkey_version'] ?? NULL;
    $info['redis_mode'] = $info['redis_mode'] ?? $info['Server']['redis_mode'] ?? NULL;
    $info['connected_clients'] = $info['connected_clients'] ?? $info['Clients']['connected_clients'] ?? NULL;
    $info['db_size'] = $this->activeClient()->dbSize();
    $info['used_memory'] = $info['used_memory'] ?? $info['Memory']['used_memory'] ?? NULL;
    $info['used_memory_human'] = $info['used_memory_human'] ?? $info['Memory']['used_memory_human'] ?? NULL;

    if (empty($info['maxmemory_policy'])) {
      $memory_config = $this->activeClient()->config('get', 'maxmemory*');
      $info['maxmemory_policy'] = $memory_config['maxmemory-policy'];
      $info['maxmemory'] = $memory_config['maxmemory'];
    }

    $info['uptime_in_seconds'] = $info['uptime_in_seconds'] ?? $info['Server']['uptime_in_seconds'] ?? NULL;
    $info['total_net_output_bytes'] = $info['total_net_output_bytes'] ?? $info['Stats']['total_net_output_bytes'] ?? NULL;
    $info['total_net_input_bytes'] = $info['total_net_input_bytes'] ?? $info['Stats']['total_net_input_bytes'] ?? NULL;
    $info['total_commands_processed'] = $info['total_commands_processed'] ?? $info['Stats']['total_commands_processed'] ?? NULL;
    $info['total_connections_received'] = $info['total_connections_received'] ?? $info['Stats']['total_connections_received'] ?? NULL;

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Predis';
  }

  /**
   * {@inheritdoc}
   */
  public function __call(string $name, array $arguments) {
    $result = $this->activeClient()->$name(...$arguments);
    if ($result instanceof Status) {
      return $result->getPayload();
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function addIgnorePattern(string $key): void {
  }

  /**
   * Returns the active pipeline or the current client.
   *
   * @return \Predis\Client|\Predis\Pipeline\Pipeline
   */
  protected function activeClient(): Client|Pipeline {
    return $this->pipeline ?: $this->redis;
  }

}
