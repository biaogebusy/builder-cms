<?php

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;

/**
 * Factory for the relay client adapter.
 */
class RelayFactory implements RedisClientFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return class_exists(\Relay\Relay::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(array $settings): ClientInterface {
    $relay = new \Relay\Relay();

    $host = $settings['host'];
    $port = $settings['port'];

    // Sentinel mode, get the real master.
    if (is_array($host)) {
      $ip_host = $this->askForMaster($relay, $settings);
      if (is_array($ip_host)) {
        [$host, $port] = $ip_host;
      }
    }

    if (!empty($settings['persistent'])) {
      $relay->pconnect($host, $port);
    }
    else {
      $relay->connect($host, $port);
    }

    if (isset($settings['password'])) {
      $relay->auth($settings['password']);
    }

    if (isset($settings['base'])) {
      $relay->select($settings['base']);
    }

    // Do not allow Relay serialize itself data, we are going to do it
    // ourself. This will ensure less memory footprint on Redis size when
    // we will attempt to store small values.
    $relay->setOption($relay::OPT_SERIALIZER, $relay::SERIALIZER_NONE);

    return new Relay($relay);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'Relay';
  }

  /**
   * Connect to sentinels to get Redis master instance.
   *
   * Just asking one sentinels after another until given the master location.
   * More info about this mode at https://redis.io/topics/sentinel.
   *
   * @param \Redis $client
   *   The PhpRedis client.
   * @param array $settings
   *   The configuration settings.
   *
   * @return mixed
   *   An array with ip & port of the Master instance or NULL.
   */
  protected function askForMaster(\Relay\Relay $client, $settings) {

    assert(is_array($settings['host']));

    $ip_port = NULL;
    $settings += ['instance' => NULL];

    if ($settings['instance']) {
      foreach ($settings['host'] as $sentinel) {
        [$host, $port] = explode(':', $sentinel);
        // Prevent fatal PHP errors when one of the sentinels is down.
        set_error_handler(function () {
          return TRUE;
        });
        // 0.5s timeout.
        $success = $client->connect($host, $port, 0.5);
        restore_error_handler();

        if (!$success) {
          continue;
        }

        if (isset($settings['password'])) {
          $client->auth($settings['password']);
        }

        if ($client->isConnected()) {
          $ip_port = $client->rawcommand('SENTINEL', 'get-master-addr-by-name', $settings['instance']);
          if ($ip_port) {
            break;
          }
        }
        $client->close();
      }
    }
    return $ip_port;
  }

}
