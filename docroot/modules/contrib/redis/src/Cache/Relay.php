<?php

namespace Drupal\redis\Cache;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Site\Settings;

/**
 * Relay cache backend.
 */
class Relay extends CacheBase {

  /**
   * @var \Relay\Relay
   */
  protected $client;

  /**
   * Creates a Relay cache backend.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   * @param \Relay\Relay $client
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   * @param \Drupal\redis\Cache\SerializationInterface $serializer
   *   The serialization class to use.
   */
  public function __construct($bin, \Relay\Relay $client, CacheTagsChecksumInterface $checksum_provider, SerializationInterface $serializer) {
    parent::__construct($bin, $serializer);
    $this->client = $client;
    $this->checksumProvider = $checksum_provider;

    // Exclude bins that should not be kept in memory
    if (!$this->keepBinInMemory())
    $this->client->setOption(
      $this->client::OPT_IGNORE_PATTERNS,
      array_unique(array_merge(
        $this->client->getOption($this->client::OPT_IGNORE_PATTERNS),
        [$this->getKey('*')]
      ))
    );
  }

  /**
   * Returns whether this cache bin should be kept in memory.
   *
   * @return bool
   *   TRUE if the Relay memory cache should be used.
   */
  protected function keepBinInMemory(): bool {
    $in_memory_bins = Settings::get('redis_relay_memory_bins', ['container', 'bootstrap', 'config', 'discovery']);
    return in_array($this->bin, $in_memory_bins);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    // Avoid an error when there are no cache ids.
    if (empty($cids)) {
      return [];
    }

    $return = [];

    // Build the list of keys to fetch.
    $keys = array_map([$this, 'getKey'], $cids);

    $result = [];
    foreach ($keys as $key) {
      $result[] = $this->client->hgetall($key);
    }

    // Loop over the cid values to ensure numeric indexes.
    foreach (array_values($cids) as $index => $key) {
      // Check if a valid result was returned from Redis.
      if (isset($result[$index]) && is_array($result[$index])) {
        // Check expiration and invalidation and convert into an object.
        $item = $this->expandEntry($result[$index], $allow_invalid);
        if ($item) {
          $return[$item->cid] = $item;
        }
      }
    }

    // Remove fetched cids from the list.
    $cids = array_diff($cids, array_keys($return));

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {

    $ttl = $this->getExpiration($expire);

    $key = $this->getKey($cid);

    // If the item is already expired, delete it.
    if ($ttl <= 0) {
      $this->delete($key);
    }

    // Build the cache item and save it as a hash array.
    $entry = $this->createEntryHash($cid, $data, $expire, $tags);
    if ($pipe = $this->client->multi()) {
      $pipe->hMset($key, $entry);
      $pipe->expire($key, $ttl);
      $pipe->exec();
    }
    else {
      trigger_error('Unable to start pipeline to write cache', E_USER_WARNING);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doDeleteMultiple(array $cids) {
    $keys = array_map([$this, 'getKey'], $cids);
    $this->client->del($keys);
  }

}
