<?php

namespace Drupal\memcache\Connection;

use Drupal\memcache\MemcacheSettings;

/**
 * Class MemcachedConnection.
 */
class MemcachedConnection implements MemcacheConnectionInterface {

  /**
   * The memcache object.
   *
   * @var \Memcached
   */
  protected $memcache;

  /**
   * Constructs a MemcachedConnection object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The memcache config object.
   */
  public function __construct(MemcacheSettings $settings) {
    $this->memcache = new \Memcached();

    $default_opts = [
      \Memcached::OPT_COMPRESSION => TRUE,
      \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
    ];
    foreach ($default_opts as $key => $value) {
      $this->memcache->setOption($key, $value);
    }
    // See README.txt for setting custom Memcache options when using the
    // memcached PECL extension.
    foreach ($settings->get('options', []) as $key => $value) {
      $this->memcache->setOption($key, $value);
    }

    // SASL configuration to authenticate with Memcached.
    // Note: this only affects the Memcached PECL extension.
    if ($sasl_config = $settings->get('sasl', [])) {
      $this->memcache->setSaslAuthData($sasl_config['username'], $sasl_config['password']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addServer($server_path, $persistent = FALSE) {
    list($host, $port) = explode(':', $server_path);

    if ($host == 'unix') {
      // Memcached expects just the path to the socket without the protocol.
      $host = substr($server_path, 7);
      // Port is always 0 for unix sockets.
      $port = 0;
    }

    return $this->memcache->addServer($host, $port, $persistent);
  }

  /**
   * {@inheritdoc}
   */
  public function getMemcache() {
    return $this->memcache;
  }

  /**
   * {@inheritdoc}
   */
  public function close() {
    $this->memcache->quit();
  }

}
