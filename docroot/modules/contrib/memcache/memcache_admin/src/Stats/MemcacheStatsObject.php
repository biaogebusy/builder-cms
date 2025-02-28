<?php

namespace Drupal\memcache_admin\Stats;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class MemcacheStats.
 *
 * @package Drupal\memcache_admin\Stats
 */
class MemcacheStatsObject implements MemcacheStatsInterface {

  use StringTranslationTrait;

  /**
   * Stat Not Available
   */
  CONST NA = 'n/a';

  /**
   * @var array $stats
   */
  protected $stats;

  public function __construct(array $raw_stats) {
    $this->stats = $raw_stats;
  }

  /**
   * @inheritDoc
   */
  public function getUptime(): string {
    return isset($this->stats['uptime']) ? \Drupal::service('date.formatter')->formatInterval($this->stats['uptime']) : self::NA;
  }

  /**
   * @inheritDoc
   */
  public function getExtension(): string {
    return $this->stats['extension'] ?? self::NA;
  }

  /**
   * @inheritDoc
   */
  public function getServerTime(): string {
    return isset($this->stats['time']) ? \Drupal::service('date.formatter')->format($this->stats['time']) : self::NA;
  }

  /**
   * Statistics report: format total and open connections.
   */
  public function getConnections() {
    if (!isset($this->stats['curr_connections']) ||
      !isset($this->stats['total_connections'])
    ) {
      return self::NA;
    }

    return $this->t(
      '@current open of @total total',
      [
        '@current' => number_format($this->stats['curr_connections']),
        '@total'   => number_format($this->stats['total_connections']),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getCurrentConnections(): string {
    return $this->stats['curr_connections'] ?? '0';
  }

  /**
   * @inheritDoc
   */
  public function getTotalConnections(): string {
    return $this->stats['total_connections'] ?? '0';
  }

  /**
   * Statistics report: calculate # of set cmds and total cmds.
   */
  public function getSets(): string {
    if (!isset($this->stats['cmd_set'])) {
      return self::NA;
    }

    if (($this->stats['cmd_set'] + $this->stats['cmd_get']) == 0) {
      $sets = 0;
    }
    else {
      $sets = $this->stats['cmd_set'] / ($this->stats['cmd_set'] + $this->stats['cmd_get']) * 100;
    }
    if (!isset($this->stats['uptime'])) {
      $average = 0;
    }
    else {
      $average = $sets / $this->stats['uptime'];
    }
    return $this->t(
      '@average/s; @set sets (@sets%) of @total commands',
      [
        '@average' => number_format($average, 2),
        '@sets'    => number_format($sets, 2),
        '@set'     => number_format($this->stats['cmd_set']),
        '@total'   => number_format($this->stats['cmd_set'] + $this->stats['cmd_get']),
      ]
    );
  }

  /**
   * Statistics report: calculate # of get cmds, broken down by hits and misses.
   */
  public function getGets(): string {
    if (!isset($this->stats['cmd_set']) || !isset($this->stats['cmd_get'])) {
      return self::NA;
    }
    else {
      $get = $this->stats['cmd_get'];
      $set = $this->stats['cmd_set'];
      $hits = $this->stats['get_hits'] ?? 0;
      $misses = $this->stats['get_misses'] ?? 0;
    }

    if (($set + $get) == 0) {
      $gets = 0;
    }
    else {
      $gets = $get / ($set + $get) * 100;
    }
    if (empty($stats['uptime'])) {
      $average = 0;
    }
    else {
      $average = $get / $stats['uptime'];
    }
    return $this->t(
      '@average/s; @total gets (@gets%); @hit hits (@percent_hit%) @miss misses (@percent_miss%)',
      [
        '@average'      => number_format($average, 2),
        '@gets'         => number_format($gets, 2),
        '@hit'          => number_format($hits),
        '@percent_hit'  => ($get > 0 ? number_format($hits / $get * 100, 2) : '0.00'),
        '@miss'         => number_format($misses),
        '@percent_miss' => ($get > 0 ? number_format($misses / $get * 100, 2) : '0.00'),
        '@total'        => number_format($get),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getCounters(): string {
    $incr_hits = $this->stats['incr_hits'] ?? 0;
    $incr_misses = $this->stats['incr_misses'] ?? 0;
    $decr_hits  = $this->stats['decr_hits'] ?? 0;
    $decr_misses = $this->stats['decr_misses'] ?? 0;

    return $this->t(
      '@incr increments, @decr decrements',
      [
        '@incr' => number_format($incr_hits + $incr_misses),
        '@decr' => number_format($decr_hits + $decr_misses),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getTransferred(): string {
    $read = $this->stats['bytes_read'] ?? 0;
    $write = $this->stats['bytes_written'] ?? 0;

    if ($write == 0) {
      $written = 0;
    }
    else {
      $written = $read / $write * 100;
    }
    return $this->t(
      '@to:@from (@written% to cache)',
      [
        '@to'      => format_size((int) $read),
        '@from'    => format_size((int) $write),
        '@written' => number_format($written, 2),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getConnectionAvg(): string {
    if (!isset($this->stats['total_connections']) ||
        !isset($this->stats['cmd_get']) ||
        !isset($this->stats['cmd_set']) ||
        !isset($this->stats['bytes_written']) ||
        !isset($this->stats['bytes_read'])
    ) {
      return self::NA;
    }
    if ($this->stats['total_connections'] == 0) {
      $get   = 0;
      $set   = 0;
      $read  = 0;
      $write = 0;
    }
    else {
      $get   = $this->stats['cmd_get'] / $this->stats['total_connections'];
      $set   = $this->stats['cmd_set'] / $this->stats['total_connections'];
      $read  = $this->stats['bytes_written'] / $this->stats['total_connections'];
      $write = $this->stats['bytes_read'] / $this->stats['total_connections'];
    }
    return $this->t(
      '@read in @get gets; @write in @set sets',
      [
        '@get'   => number_format($get, 2),
        '@set'   => number_format($set, 2),
        '@read'  => format_size((int) number_format($read, 2)),
        '@write' => format_size((int) number_format($write, 2)),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getMemory(): string {
    if (!isset($this->stats['limit_maxbytes']) ||
        !isset($this->stats['bytes'])
    ) {
      return self::NA;
    }

    if ($this->stats['limit_maxbytes'] == 0) {
      $percent = 0;
    }
    else {
      $percent = 100 - $this->stats['bytes'] / $this->stats['limit_maxbytes'] * 100;
    }
    return $this->t(
      '@available (@percent%) of @total',
      [
        '@available' => format_size($this->stats['limit_maxbytes'] - $this->stats['bytes']),
        '@percent'   => number_format($percent, 2),
        '@total'     => format_size($this->stats['limit_maxbytes']),
      ]
    );
  }

  /**
   * @inheritDoc
   */
  public function getEvictions(): string {
    return isset($this->stats['evictions']) ? number_format($this->stats['evictions']) : self::NA;
  }

  /**
   * @inheritDoc
   */
  public function setRaw(array $raw_data) {
    $this->stats = $raw_data;
  }

  /**
   * @inheritDoc
   */
  public function getRaw(): array {
    return $this->stats;
  }

  /**
   * @inheritDoc
   */
  public function getVersion(): string {
    return isset($this->stats['version']) ? (string)$this->stats['version'] : self::NA;
  }

}
