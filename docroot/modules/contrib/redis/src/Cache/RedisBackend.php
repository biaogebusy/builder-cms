<?php

namespace Drupal\redis\Cache;

use DateInterval;
use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsChecksumPreloadInterface;
use Drupal\Core\Cache\ChainedFastBackend;
use Drupal\Core\Site\Settings;
use Drupal\redis\ClientInterface;
use Drupal\redis\RedisPrefixTrait;

/**
 * Base class for redis cache backends.
 */
class RedisBackend implements CacheBackendInterface {

  use RedisPrefixTrait;

  /**
   * Default lifetime for permanent items.
   * Approximatively 1 year.
   */
  const LIFETIME_PERM_DEFAULT = 31536000;

  /**
   * Latest delete all flush KEY name.
   */
  const LAST_DELETE_ALL_KEY = '_redis_last_delete_all';

  /**
   * Default TTL for CACHE_PERMANENT items.
   *
   * See "Default lifetime for permanent items" section of README.md
   * file for a comprehensive explanation of why this exists.
   *
   * @var int
   */
  protected int $permTtl = self::LIFETIME_PERM_DEFAULT;

  /**
   * The last delete timestamp.
   *
   * @var float
   */
  protected $lastDeleteAll = NULL;

  /**
   * Delayed deletions for deletions during a transaction.
   *
   * @var string[]
   */
  protected $delayedDeletions = [];

  /**
   * Get TTL for CACHE_PERMANENT items.
   *
   * @return int
   *   Lifetime in seconds.
   */
  public function getPermTtl() {
    return $this->permTtl;
  }

  public function __construct(protected string $bin, protected ClientInterface $client, protected CacheTagsChecksumInterface $checksumProvider, protected SerializationInterface $serializer) {
    $this->setPermTtl();

    // Exclude bins that should not be kept in memory.
    if (!$this->isPermanentBin()) {
      $this->client->addIgnorePattern($this->getKey('*'));
    }
  }

  /**
   * Returns whether this bin is small and should be considered permanent.
   *
   * Values in this bin by default do not get a TTL and will not be evicted
   * when using a volatile eviction policy.
   *
   * It is critical that these bins are small and guaranteed to fit into
   * the available memory.
   *
   * This used to be relay specific setting, and is also used to keep those
   * in memory.
   *
   * @return bool
   *   TRUE if this is a persistent bin.
   */
  protected function isPermanentBin(): bool {
    $in_memory_bins = Settings::get('redis_permanent_bins', Settings::get('redis_relay_memory_bins', ['container', 'bootstrap', 'config', 'discovery']));
    return in_array($this->bin, $in_memory_bins);
  }

  /**
   * Checks whether the cache id is the last write timestamp.
   *
   * Cache requests for this are streamlined to bypass the full cache API as
   * that needs two extra requests to check for delete or invalidate all flags.
   *
   * Most requests will only fetch this single timestamp from bins using the
   * ChainedFast backend.
   *
   * @param string $cid
   *   The requested cache id.
   *
   * @return bool
   */
  protected function isLastWriteTimestamp(string $cid): bool {
    return $cid === ChainedFastBackend::LAST_WRITE_TIMESTAMP_PREFIX . 'cache_' . $this->bin;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {

    if ($this->isLastWriteTimestamp($cid)) {
      $timestamp = $this->client->get($this->getPrefix() . ':' . $cid);
      return $timestamp ? (object) ['data' => $timestamp] : NULL;
    }

    $cids = [$cid];
    $cache = $this->getMultiple($cids, $allow_invalid);
    return reset($cache);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    $tags = [];
    // Always add a cache tag for the current bin, so that we can use that for
    // invalidateAll().
    if (Settings::get('redis_invalidate_all_as_delete', TRUE) === FALSE) {
      $tags[] = [$this->getTagForBin()];
    }

    // Prepare items and delete already expired ones outside the pipeline,
    // preload all cache tags outside the pipeline.
    foreach ($items as $cid => $item) {
      $item += [
        'expire' => CacheBackendInterface::CACHE_PERMANENT,
        'tags' => [],
      ];

      $item['ttl'] = $this->getExpiration($item['expire']);

      // If the item is already expired, delete it.
      if (isset($item['ttl']) && $item['ttl'] <= 0) {
        $key = $this->getKey($cid);
        $this->delete($key);
        unset($items[$cid]);
      }

      if (!empty($item['tags'])) {
        assert(Inspector::assertAllStrings($item['tags']), 'Cache Tags must be strings.');
        $tags[] = $item['tags'];
      }

      $items[$cid] = $item;
    }

    if ($tags) {
      // Preload all cache tags outside the pipeline.
      $this->checksumProvider->getCurrentChecksum(array_merge(...$tags));
    }

    // It is not possible to have multiple pipelines, prepare the hash entries
    // to avoid that anything during serialization may trigger another pipeline
    // due to a fiber, or nested cache read or write while this is within a
    // pipeline.
    $ttls = [];
    $entries = [];
    foreach ($items as $cid => $item) {
      $key = $this->getKey($cid);
      $ttls[$key] = $item['ttl'];

      // Build the cache item and save it as a hash array.
      $entries[$key] = $this->createEntryHash($cid, $item['data'], $item['expire'], $item['tags']);
    }

    if (!$entries) {
      return;
    }

    // Write the hash sets with the respective expiration.
    $this->client->pipeline();
    foreach ($entries as $key => $entry) {
      $this->client->hMset($key, $entry);
      if (isset($ttls[$key])) {
        $this->client->expire($key, $ttls[$key]);
      }
    }
    $this->client->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->deleteMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    /** @phpstan-ignore-next-line */
    $database = \Drupal::hasContainer() ? \Drupal::database() : NULL;
    $in_transaction = $database?->inTransaction();
    if ($in_transaction) {
      if (empty($this->delayedDeletions)) {
        if (method_exists($database, 'transactionManager')) {
          $database->transactionManager()->addPostTransactionCallback([$this, 'postRootTransactionCommit']);
        }
        else {
          /** @phpstan-ignore-next-line */
          $database->addRootTransactionEndCallback([$this, 'postRootTransactionCommit']);
        }
      }
      $this->delayedDeletions = array_unique(array_merge($this->delayedDeletions, $cids));
    }
    elseif ($cids) {
      $keys = array_map([$this, 'getKey'], $cids);
      $this->client->del($keys);
    }
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

    $this->client->pipeline();
    foreach ($keys as $key) {
      $this->client->hgetall($key);
    }
    $result = $this->client->exec();

    // Before checking the validity of each item individually, register the
    // cache tags for all returned cache items for preloading, this allows the
    // cache tag service to optimize cache tag lookups.
    if (method_exists($this->checksumProvider, 'registerCacheTagsForPreload')) {
      $tags_for_preload = [];
      foreach ($result as $item) {
        if (!empty($item['tags'])) {
          $tags_for_preload[] = explode(' ', $item['tags']);
        }
      }
      $this->checksumProvider->registerCacheTagsForPreload(array_merge(...$tags_for_preload));
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

    if ($this->isLastWriteTimestamp($cid)) {
      $this->client->set($this->getPrefix() . ':' . $cid, $data);
      return;
    }

    $this->setMultiple([
      $cid => [
        'data' => $data,
        'expire' => $expire,
        'tags' => $tags,
      ],
    ]);
  }

  /**
   * Callback to be invoked after a database transaction gets committed.
   *
   * Invalidates all delayed cache deletions.
   *
   * @param bool $success
   *   Whether or not the transaction was successful.
   */
  public function postRootTransactionCommit($success) {
    if ($success && $this->delayedDeletions) {
      $keys = array_map([$this, 'getKey'], $this->delayedDeletions);
      $this->client->del($keys);
    }
    $this->delayedDeletions = [];
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    $this->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * Return the key for the given cache key.
   */
  public function getKey($cid = NULL) {
    if (NULL === $cid) {
      return $this->getPrefix() . ':' . $this->bin;
    }
    else {
      return $this->getPrefix() . ':' . $this->bin . ':' . $cid;
    }
  }

  /**
   * Calculate the correct ttl value for redis.
   *
   * @param int $expire
   *   The expiration time provided for the cache set.
   *
   * @return int|null
   *   The default TTL if expire is PERMANENT or higher than the default.
   *   Otherwise, the adjusted lifetime of the cache if setting
   *   redis_ttl_offset is set >= 0. May return negative values if the item
   *   is already expired. May return NULL if the item should not have expire
   *   at all, typically because this is a permanent bin.
   */
  protected function getExpiration($expire): int|null {
    $redis_ttl_offset = Settings::get('redis_ttl_offset', 3600);
    if ($expire == Cache::PERMANENT || $redis_ttl_offset === NULL) {
      return $this->permTtl === Cache::PERMANENT ? NULL : $this->permTtl;
    }

    /** @phpstan-ignore-next-line */
    $expire_ttl = $expire - \Drupal::time()->getRequestTime();
    if ($this->permTtl != self::CACHE_PERMANENT && $expire_ttl > $this->permTtl) {
      return $this->permTtl;
    }

    return $expire_ttl + $redis_ttl_offset;
  }

  /**
   * Return the key for the tag used to specify the bin of cache-entries.
   */
  protected function getTagForBin() {
    return 'x-redis-bin:' . $this->bin;
  }

  /**
   * Set the permanent TTL.
   */
  public function setPermTtl(?int $ttl = NULL) {
    if (isset($ttl)) {
      $this->permTtl = $ttl;
    }
    else {
      // Attempt to set from settings, fall back to old settings key.
      $ttl = Settings::get('redis_perm_ttl_' . $this->bin);
      if ($ttl === NULL) {
        $ttl = Settings::get('redis.settings', [])['perm_ttl_' . $this->bin] ?? NULL;
      }
      if ($ttl === NULL && $this->isPermanentBin()) {
        $ttl = CacheBackendInterface::CACHE_PERMANENT;
      }
      if ($ttl) {
        if ($ttl === (int) $ttl) {
          $this->permTtl = (int) $ttl;
        }
        else {
          if ($iv = DateInterval::createFromDateString($ttl)) {
            // http://stackoverflow.com/questions/14277611/convert-dateinterval-object-to-seconds-in-php
            $this->permTtl = ($iv->y * 31536000 + $iv->m * 2592000 + $iv->d * 86400 + $iv->h * 3600 + $iv->i * 60 + $iv->s);
          }
          else {
            // Log error about invalid ttl.
            trigger_error(sprintf("Parsed TTL '%s' has an invalid value: switching to default", $ttl));
            $this->permTtl = self::LIFETIME_PERM_DEFAULT;
          }

        }
      }
    }
  }

  /**
   * Prepares a cached item.
   *
   * Checks that items are either permanent or did not expire, and unserializes
   * data as appropriate.
   *
   * @param array $values
   *   The hash returned from redis or false.
   * @param bool $allow_invalid
   *   If FALSE, the method returns FALSE if the cache item is not valid.
   *
   * @return mixed|false
   *   The item with data unserialized as appropriate and a property indicating
   *   whether the item is valid, or FALSE if there is no valid item to load.
   */
  protected function expandEntry(array $values, $allow_invalid) {
    // Check for entry being valid.
    if (empty($values['cid'])) {
      return FALSE;
    }

    // Ignore items that are scheduled for deletion.
    if (in_array($values['cid'], $this->delayedDeletions)) {
      return FALSE;
    }

    $cache = (object) $values;

    $cache->tags = $cache->tags ? explode(' ', $cache->tags) : [];

    // Check expire time, allow to have a cache invalidated explicitly, don't
    // check if already invalid.
    if ($cache->valid) {
      /** @phpstan-ignore-next-line */
      $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= \Drupal::time()->getRequestTime();

      // Check if invalidateTags() has been called with any of the items's tags.
      if ($cache->valid && !$this->checksumProvider->isValid($cache->checksum, $cache->tags)) {
        $cache->valid = FALSE;
      }

      if (Settings::get('redis_invalidate_all_as_delete', TRUE) === FALSE) {
        // Remove the bin cache tag to not expose that, otherwise it is reused
        // by the fast backend in the FastChained implementation.
        $cache->tags = array_diff($cache->tags, [$this->getTagForBin()]);
      }
    }

    // Ensure the entry does not predate the last delete all time.
    $last_delete_timestamp = $this->getLastDeleteAll();
    if ($last_delete_timestamp && ((float) $values['created']) < $last_delete_timestamp) {
      return FALSE;
    }

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    if (!empty($cache->gz)) {
      // Uncompress, suppress warnings e.g. for broken CRC32.
      $cache->data = @gzuncompress($cache->data);
      // In such cases, void the cache entry.
      if ($cache->data === FALSE) {
        return FALSE;
      }
    }

    if ($cache->serialized) {
      $cache->data = $this->serializer->decode($cache->data);
    }

    return $cache;
  }

  /**
   * Create cache entry.
   *
   * @param string $cid
   * @param mixed $data
   * @param int $expire
   * @param string[] $tags
   *
   * @return array
   */
  protected function createEntryHash($cid, $data, $expire, array $tags) {
    // Always add a cache tag for the current bin, so that we can use that for
    // invalidateAll().
    if (Settings::get('redis_invalidate_all_as_delete', TRUE) === FALSE) {
      $tags[] = $this->getTagForBin();
    }
    $hash = [
      'cid' => $cid,
      'created' => round(microtime(TRUE), 3),
      'expire' => $expire,
      'tags' => implode(' ', $tags),
      'valid' => 1,
      'checksum' => $this->checksumProvider->getCurrentChecksum($tags),
    ];

    // Encode anything that is not a strong.
    if (!is_string($data)) {
      $hash['data'] = $this->serializer->encode($data);
      $hash['serialized'] = 1;
    }
    else {
      $hash['data'] = $data;
      $hash['serialized'] = 0;
    }

    if (Settings::get('redis_compress_length', 1000) && strlen($hash['data']) > Settings::get('redis_compress_length', 1000)) {
      $hash['data'] = @gzcompress($hash['data'], Settings::get('redis_compress_level', 1));
      $hash['gz'] = TRUE;
    }

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    // Loop over all cache items, they are stored as a hash, so we can access
    // the valid flag directly, only write if it exists and is not 0.
    foreach ($cids as $cid) {
      $key = $this->getKey($cid);
      if ($this->client->hGet($key, 'valid')) {
        $this->client->hSet($key, 'valid', 0);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    @trigger_error("CacheBackendInterface::invalidateAll() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead. See https://www.drupal.org/node/3500622", E_USER_DEPRECATED);
    if (Settings::get('redis_invalidate_all_as_delete', TRUE) === FALSE) {
      // To invalidate the whole bin, we invalidate a special tag for this bin.
      $this->checksumProvider->invalidateTags([$this->getTagForBin()]);
    }
    else {
      // If the optimization for invalidate all is enabled, treat it as a
      // deleteAll() so we only have to check one thing.
      $this->deleteAll();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    // Garbage collection is handled by Redis.
  }

  /**
   * Returns the last delete all timestamp.
   *
   * @return float
   *   The last delete timestamp as a timestamp with a millisecond precision.
   */
  protected function getLastDeleteAll() {
    // Cache the last delete all timestamp.
    if ($this->lastDeleteAll === NULL) {
      $this->lastDeleteAll = (float) $this->client->get($this->getKey(static::LAST_DELETE_ALL_KEY));
    }
    return $this->lastDeleteAll;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    // The last delete timestamp is in milliseconds, ensure that no cache
    // was written in the same millisecond.
    // @todo This is needed to make the tests pass, is this safe enough for real
    //   usage?
    usleep(1000);
    $this->lastDeleteAll = round(microtime(TRUE), 3);
    $this->client->set($this->getKey(static::LAST_DELETE_ALL_KEY), $this->lastDeleteAll);
  }

}
