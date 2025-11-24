<?php

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;

/**
 * Relay client specific implementation.
 */
class Relay implements ClientInterface {

  public function __construct(protected \Relay\Relay $redis) {}

  /**
   * {@inheritdoc}
   */
  public function scan(string $match, int $count = 1000) {
    $it = NULL;
    while ($keys = $this->redis->scan($it, $match, $count)) {
      yield from $keys;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function info(): array {
    $info = $this->redis->info();

    $info['redis_version'] = $info['redis_version'] ?? $info['Server']['redis_version'] ?? NULL;
    $info['valkey_version'] = $info['valkey_version'] ?? NULL;
    $info['redis_mode'] = $info['redis_mode'] ?? $info['Server']['redis_mode'] ?? NULL;
    $info['connected_clients'] = $info['connected_clients'] ?? $info['Clients']['connected_clients'] ?? NULL;
    $info['db_size'] = $this->redis->dbSize();
    $info['used_memory'] = $info['used_memory'] ?? $info['Memory']['used_memory'] ?? NULL;
    $info['used_memory_human'] = $info['used_memory_human'] ?? $info['Memory']['used_memory_human'] ?? NULL;

    if (empty($info['maxmemory_policy'])) {
      $memory_config = $this->redis->config('get', 'maxmemory*');
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
    return 'Relay';
  }

  /**
   * {@inheritdoc}
   */
  public function __call(string $name, array $arguments) {
    return $this->redis->$name(...$arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function addIgnorePattern(string $key): void {
    $this->redis->setOption(
      \Relay\Relay::OPT_IGNORE_PATTERNS,
      array_unique(array_merge(
        $this->redis->getOption(\Relay\Relay::OPT_IGNORE_PATTERNS),
        [$key]
      ))
    );
  }

}
