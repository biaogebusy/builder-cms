<?php

namespace Drupal\memcache\Driver;

use Drupal\Component\Utility\Timer;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\memcache\MemcacheSettings;
use Drupal\memcache\DrupalMemcacheInterface;

/**
 * Class DriverBase.
 */
abstract class DriverBase implements DrupalMemcacheInterface {

  use LoggerChannelTrait;

  /**
   * The memcache config object.
   *
   * @var \Drupal\memcache\MemcacheSettings
   */
  protected $settings;

  /**
   * The memcache object.
   *
   * @var \Memcache|\Memcached
   *   E.g. \Memcache|\Memcached
   */
  protected $memcache;

  /**
   * The hash algorithm to pass to hash(). Defaults to 'sha1'.
   *
   * @var string
   */
  protected $hashAlgorithm;

  /**
   * The prefix memcache key for all keys.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Stats for the entire request.
   *
   * @var array
   */
  protected static $stats = [
    'all' => [],
    'ops' => [],
  ];

  /**
   * Constructs a DriverBase object.
   *
   * @param \Drupal\memcache\MemcacheSettings $settings
   *   The memcache config object.
   * @param \Memcached|\Memcache $memcache
   *   An existing memcache connection object.
   * @param string $bin
   *   The class instance specific cache bin to use.
   */
  public function __construct(MemcacheSettings $settings, $memcache, $bin = NULL) {
    $this->settings = $settings;
    $this->memcache = $memcache;

    $this->hashAlgorithm = $this->settings->get('key_hash_algorithm', 'sha1');

    $prefix = $this->settings->get('key_prefix', '');
    if ($prefix) {
      $this->prefix = $prefix . ':';
    }

    if ($bin) {
      $this->prefix .= $bin . ':';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    $collect_stats = $this->statsInit();

    $full_key = $this->key($key);
    $result   = $this->memcache->get($full_key);

    if ($collect_stats) {
      $this->statsWrite('get', 'cache', [$full_key => (bool) $result]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function key($key) {
    $full_key = urlencode($this->prefix . '-' . $key);

    // Memcache only supports key lengths up to 250 bytes.  If we have generated
    // a longer key, we shrink it to an acceptable length with a configurable
    // hashing algorithm. Sha1 was selected as the default as it performs
    // quickly with minimal collisions.
    if (strlen($full_key) > 250) {
      $full_key = urlencode($this->prefix . '-' . hash($this->hashAlgorithm, $key));
      $full_key .= '-' . substr(urlencode($key), 0, (250 - 1) - strlen($full_key) - 1);
    }

    return $full_key;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $collect_stats = $this->statsInit();

    $full_key = $this->key($key);
    $result = $this->memcache->delete($full_key, 0);

    if ($collect_stats) {
      $this->statsWrite('delete', 'cache', [$full_key => $result]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function flush() {
    $collect_stats = $this->statsInit();

    $result = $this->memcache->flush();

    if ($collect_stats) {
      $this->statsWrite('flush', 'cache', ['' => $result]);
    }
    return $result;
  }

  /**
   * Retrieves statistics recorded during memcache operations.
   *
   * @param string $stats_bin
   *   The bin to retrieve statistics for.
   * @param string $stats_type
   *   The type of statistics to retrieve when using the Memcache extension.
   * @param bool $aggregate
   *   Whether to aggregate statistics.
   */
  public function stats($stats_bin = 'cache', $stats_type = 'default', $aggregate = FALSE) {

    // The stats_type can be over-loaded with an integer slab id, if doing a
    // cachedump.  We know we're doing a cachedump if $slab is non-zero.
    $slab = (int) $stats_type;
    $stats = [];

    foreach ($this->getBins() as $bin => $target) {
      if ($stats_bin == $bin) {
        if (isset($this->memcache)) {
          if ($this->memcache instanceof \Memcached) {
            $stats[$bin] = $this->memcache->getStats();
          }

          // The PHP Memcache extension 3.x version throws an error if the stats
          // type is NULL or not in {reset, malloc, slabs, cachedump, items,
          // sizes}. If $stats_type is 'default', then no parameter should be
          // passed to the Memcache memcache_get_extended_stats() function.
          elseif ($this->memcache instanceof \Memcache) {
            if ($stats_type == 'default' || $stats_type == '') {
              $stats[$bin] = $this->memcache->getExtendedStats();
            }

            // If $slab isn't zero, then we are dumping the contents of a
            // specific cache slab.
            elseif (!empty($slab)) {
              $stats[$bin] = $this->memcache->getStats('cachedump', $slab);
            }
            else {
              $stats[$bin] = $this->memcache->getExtendedStats($stats_type);
            }
          }
        }
      }
    }

    // Optionally calculate a sum-total for all servers in the current bin.
    if ($aggregate) {

      // Some variables don't logically aggregate.
      $no_aggregate = [
        'pid',
        'time',
        'version',
        'libevent',
        'pointer_size',
        'accepting_conns',
        'listen_disabled_num',
      ];

      foreach ($stats as $bin => $servers) {
        if (is_array($servers)) {
          foreach ($servers as $server) {
            if (is_array($server)) {
              foreach ($server as $key => $value) {
                if (!in_array($key, $no_aggregate)) {
                  if (isset($stats[$bin]['total'][$key])) {
                    $stats[$bin]['total'][$key] += $value;
                  }
                  else {
                    $stats[$bin]['total'][$key] = $value;
                  }
                }
              }
            }
          }
        }
      }
    }

    return $stats;
  }

  /**
   * Helper function to get the bins.
   */
  public function getBins() {
    $memcache_bins = \Drupal::configFactory()->getEditable('memcache.settings')->get('memcache_bins');
    if (!isset($memcache_bins)) {
      $memcache_bins = ['cache' => 'default'];
    }

    return $memcache_bins;
  }

  /**
   * Helper function to get the servers.
   */
  public function getServers() {
    $memcache_servers = \Drupal::configFactory()->getEditable('memcache.settings')->get('memcache_servers');
    if (!isset($memcache_servers)) {
      $memcache_servers = ['127.0.0.1:11211' => 'default'];
    }

    return $memcache_servers;
  }

  /**
   * Helper function to get memcache.
   */
  public function getMemcache() {
    return $this->memcache;
  }

  /**
   * Helper function to get request stats.
   */
  public function requestStats() {
    return self::$stats;
  }

  /**
   * Returns an array of available statistics types.
   */
  public function statsTypes() {
    if ($this->memcache instanceof \Memcache) {
      // TODO: Determine which versions of the PECL memcache extension have
      // these other stats types: 'malloc', 'maps', optionally detect this
      // version and expose them.  These stats are "subject to change without
      // warning" unfortunately.
      return ['default', 'slabs', 'items', 'sizes'];
    }
    else {
      // The Memcached PECL extension only offers the default statistics.
      return ['default'];
    }
  }

  /**
   * Helper function to initialize the stats for a memcache operation.
   */
  protected function statsInit() {
    static $drupal_static_fast;

    if (!isset($drupal_static_fast)) {
      $drupal_static_fast = &drupal_static(__FUNCTION__, ['variable_checked' => NULL, 'user_access_checked' => NULL]);
    }
    $variable_checked    = &$drupal_static_fast['variable_checked'];
    $user_access_checked = &$drupal_static_fast['user_access_checked'];

    // Confirm DRUPAL_BOOTSTRAP_VARIABLES has been reached. We don't use
    // drupal_get_bootstrap_phase() as it's buggy. We can use variable_get()
    // here because _drupal_bootstrap_variables() includes module.inc
    // immediately after it calls variable_initialize().
    // @codingStandardsIgnoreStart
    // if (!isset($variable_checked) && function_exists('module_list')) {
    //   $variable_checked = variable_get('show_memcache_statistics', FALSE);
    // }
    // If statistics are enabled we need to check user access.
    // if (!empty($variable_checked) && !isset($user_access_checked) && !empty($GLOBALS['user']) && function_exists('user_access')) {
    //   // Statistics are enabled and the $user object has been populated, so check
    //   // that the user has access to view them.
    //   $user_access_checked = user_access('access memcache statistics');
    // }
    // @codingStandardsIgnoreEnd
    // Return whether or not statistics are enabled and the user can access
    // them.
    if ((!isset($variable_checked) || $variable_checked) && (!isset($user_access_checked) || $user_access_checked)) {
      Timer::start('dmemcache');
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Memcache statistics to be displayed at end of page generation.
   *
   * @param string $action
   *   The action being performed (get, set, etc...).
   * @param string $bin
   *   The memcache bin the action is being performed in.
   * @param array $keys
   *   Keyed array in the form (string)$cid => (bool)$success. The keys the
   *   action is being performed on, and whether or not it was a success.
   */
  protected function statsWrite($action, $bin, array $keys) {

    // Determine how much time elapsed to execute this action.
    $time = Timer::read('dmemcache');

    // Build the 'all' and 'ops' arrays displayed by memcache_admin.module.
    foreach ($keys as $key => $success) {
      self::$stats['all'][] = [
        number_format($time, 2),
        $action,
        $bin,
        $key,
        $success ? 'hit' : 'miss',
      ];
      if (!isset(self::$stats['ops'][$action])) {
        self::$stats['ops'][$action] = [$action, 0, 0, 0];
      }
      self::$stats['ops'][$action][1] += $time;
      if ($success) {
        self::$stats['ops'][$action][2]++;
      }
      else {
        self::$stats['ops'][$action][3]++;
      }
    }
  }

}
