<?php

namespace Drupal\memcache\Connection;

/**
 * Class MemcacheConnection.
 */
class MemcacheConnection implements MemcacheConnectionInterface {

  /**
   * The memcache object.
   *
   * @var \Memcache
   */
  protected $memcache;

  /**
   * Constructs a MemcacheConnection object.
   */
  public function __construct() {
    $this->memcache = new \Memcache();
  }

  /**
   * {@inheritdoc}
   */
  public function addServer($server_path, $persistent = FALSE) {
    list($host, $port) = explode(':', $server_path);

    // Support unix sockets in the format 'unix:///path/to/socket'.
    if ($host == 'unix') {
      // When using unix sockets with Memcache use the full path for $host.
      $host = $server_path;
      // Port is always 0 for unix sockets.
      $port = 0;
    }

    // When using the PECL memcache extension, we must use ->(p)connect
    // for the first connection.
    return $this->connect($host, $port, $persistent);
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
    $this->memcache->close();
  }

  /**
   * Connects to a memcache server.
   *
   * @param string $host
   *   The server path without port.
   * @param int $port
   *   The server port.
   * @param bool $persistent
   *   Whether this server connection is persistent or not.
   *
   * @return \Memcache|bool
   *   A Memcache object for a successful persistent connection. TRUE for a
   *   successful non-persistent connection. FALSE when the server fails to
   *   connect.
   */
  protected function connect($host, $port, $persistent) {
    if ($persistent) {
      return @$this->memcache->pconnect($host, $port);
    }
    else {
      return @$this->memcache->connect($host, $port);
    }
  }

}
