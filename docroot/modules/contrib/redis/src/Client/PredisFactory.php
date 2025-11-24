<?php

namespace Drupal\redis\Client;

use Drupal\redis\ClientInterface;
use Predis\Client;

/**
 * PhpRedis client specific implementation.
 */
class PredisFactory implements RedisClientFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return class_exists(Client::class);
  }

  public function getClient(array $settings): ClientInterface {

    foreach ($settings as $key => $value) {
      if (!isset($value)) {
        unset($settings[$key]);
      }
    }

    // I'm not sure why but the error handler is driven crazy if timezone
    // is not set at this point.
    // Hopefully Drupal will restore the right one this once the current
    // account has logged in.
    date_default_timezone_set(@date_default_timezone_get());

    // If we are passed in an array of $replicationHosts, we should attempt a clustered client connection.
    if (!empty($settings['replication.host'])) {
      $parameters = [];

      foreach ($settings['replication.host'] as $replicationHost) {
        $param = 'tcp://' . $replicationHost['host'] . ':' . $replicationHost['port']
          . '?persistent=' . (isset($settings['persistent']) ? 'true' : 'false');

        // Configure master.
        if ($replicationHost['role'] === 'primary') {
          $param .= '&alias=master';
        }

        $parameters[] = $param;
      }

      $options = ['replication' => TRUE];
      $redis = new Client($parameters, $options);
    }
    else {
      $redis = new Client($settings);
    }

    return new Predis($redis);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'Predis';
  }

}
