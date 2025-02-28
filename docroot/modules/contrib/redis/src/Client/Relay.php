<?php

namespace Drupal\redis\Client;

/**
 * Relay client specific implementation.
 */
class Relay extends PhpRedis {

  /**
   * {@inheritdoc}
   */
  public function getClient($host = NULL, $port = NULL, $base = NULL, $password = NULL, $replicationHosts = [], $persistent = FALSE) {
    $client = new \Relay\Relay();

    // Sentinel mode, get the real master.
    if (is_array($host)) {
      $ip_host = $this->askForMaster($client, $host, $password);
      if (is_array($ip_host)) {
        list($host, $port) = $ip_host;
      }
    }

    if ($persistent) {
      $client->pconnect($host, $port);
    }
    else {
      $client->connect($host, $port);
    }

    if (isset($password)) {
      $client->auth($password);
    }

    if (isset($base)) {
      $client->select($base);
    }

    // Do not allow Relay serialize itself data, we are going to do it
    // ourself. This will ensure less memory footprint on Redis size when
    // we will attempt to store small values.
    $client->setOption($client::OPT_SERIALIZER, $client::SERIALIZER_NONE);

    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Relay';
  }
}
