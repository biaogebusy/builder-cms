<?php

namespace Drupal\memcache_admin\Stats;

/**
 * Defines the Memcache connection interface.
 */
interface MemcacheStatsInterface {

  /**
   * Sets an array of raw data for the memcache server.
   *
   * @param array $raw_data
   *
   * @return void
   */
  public function setRaw(array $raw_data);

  /**
   * Returns raw data from the memcache server.
   *
   * @return array
   */
  public function getRaw(): array;

  /**
   * Returns the memcache server version.
   *
   * @return string
   */
  public function getVersion(): string;

  /**
   * Returns the uptime for the memcache server.
   *
   * @return string
   */
  public function getUptime(): string;

  /**
   * Returns the PECL extension for the memcache server.
   *
   * @return string
   */
  public function getExtension(): string;

  /**
   * Returns the total connections for the memcache server.
   *
   * @return string
   */
  public function getTotalConnections(): string;
  
  /**
   * Returns the cache sets for the memcache server.
   *
   * @return string
   */
  public function getSets(): string;

  /**
   * Returns the cache gets for the memcache server.
   *
   * @return string
   */
  public function getGets(): string;

  /**
   * Returns the counters for the memcache server.
   *
   * @return string
   */
  public function getCounters(): string;

  /**
   * Returns the data transferred for the memcache server.
   *
   * @return string
   */
  public function getTransferred(): string;

  /**
   * Returns the connection averages for the memcache server.
   *
   * @return string
   */
  public function getConnectionAvg(): string;

  /**
   * Returns the memory available for the memcache server.
   *
   * @return string
   */
  public function getMemory(): string;

  /**
   * Returns the evictions for the memcache server.
   *
   * @return string
   */
  public function getEvictions(): string;
  
}