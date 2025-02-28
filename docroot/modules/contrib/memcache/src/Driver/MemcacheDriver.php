<?php

namespace Drupal\memcache\Driver;

/**
 * Class MemcacheDriver.
 */
class MemcacheDriver extends DriverBase {

  /**
   * {@inheritdoc}
   */
  public function set($key, $value, $exp = 0, $flag = FALSE) {
    $collect_stats = $this->statsInit();

    $full_key = $this->key($key);
    $result = $this->memcache->set($full_key, $value, $flag, $exp);

    if ($collect_stats) {
      $this->statsWrite('set', 'cache', [$full_key => (int) $result]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function add($key, $value, $expire = 0) {
    $collect_stats = $this->statsInit();

    $full_key = $this->key($key);
    $result = $this->memcache->add($full_key, $value, FALSE, $expire);

    if ($collect_stats) {
      $this->statsWrite('add', 'cache', [$full_key => (int) $result]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti(array $keys) {
    $collect_stats = $this->statsInit();
    $multi_stats   = [];

    $full_keys = [];

    foreach ($keys as $key => $cid) {
      $full_key = $this->key($cid);
      $full_keys[$cid] = $full_key;

      if ($collect_stats) {
        $multi_stats[$full_key] = FALSE;
      }
    }

    $results = $this->memcache->get($full_keys);

    // If $results is FALSE, convert it to an empty array.
    if (!$results) {
      $results = [];
    }

    if ($collect_stats) {
      foreach ($multi_stats as $key => $value) {
        $multi_stats[$key] = isset($results[$key]) ? TRUE : FALSE;
      }
    }

    // Convert the full keys back to the cid.
    $cid_results = [];

    // Order isn't guaranteed, so ensure the return order matches that
    // requested. So base the results on the order of the full_keys, as they
    // reflect the order of the $cids passed in.
    foreach (array_intersect($full_keys, array_keys($results)) as $cid => $full_key) {
      $cid_results[$cid] = $results[$full_key];
    }

    if ($collect_stats) {
      $this->statsWrite('getMulti', 'cache', $multi_stats);
    }

    return $cid_results;
  }

}
