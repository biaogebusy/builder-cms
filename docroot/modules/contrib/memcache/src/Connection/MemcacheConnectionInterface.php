<?php

namespace Drupal\memcache\Connection;

/**
 * Defines the Memcache connection interface.
 */
interface MemcacheConnectionInterface {

  /**
   * Adds a memcache server.
   *
   * @param string $server_path
   *   The server path including port.
   * @param bool $persistent
   *   Whether this server connection is persistent or not.
   */
  public function addServer($server_path, $persistent = FALSE);

  /**
   * Returns the internal memcache object.
   *
   * @return object
   *   e.g. \Memcache|\Memcached
   */
  public function getMemcache();

  /**
   * Closes the memcache instance connection.
   */
  public function close();

}
