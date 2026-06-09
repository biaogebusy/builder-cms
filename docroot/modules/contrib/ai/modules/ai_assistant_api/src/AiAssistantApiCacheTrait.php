<?php

namespace Drupal\ai_assistant_api;

use Drupal\Core\Cache\Cache;

/**
 * Wrapper methods to interact with the AI Assistant API cache.
 *
 * This was specifically added to introduce caching in classes that cannot
 * have new arguments injected without breaking backwards compatibility.
 *
 * @todo Replace with dependency injection for 2.x.
 */
trait AiAssistantApiCacheTrait {

  /**
   * Stores data in the persistent cache.
   *
   * Core cache implementations set the created time on cache item with
   * microtime(TRUE) rather than REQUEST_TIME_FLOAT, because the created time
   * of cache items should match when they are created, not when the request
   * started. Apart from being more accurate, this increases the chance an
   * item will legitimately be considered valid.
   *
   * @param string $cid
   *   The cache ID of the data to store.
   * @param mixed $data
   *   The data to store in the cache.
   *   Some storage engines only allow objects up to a maximum of 1MB in size to
   *   be stored by default. When caching large arrays or similar, take care to
   *   ensure $data does not exceed this size.
   * @param int $expire
   *   One of the following values:
   *   - CacheBackendInterface::CACHE_PERMANENT: Indicates that the item should
   *     not be removed unless it is deleted explicitly.
   *   - A Unix timestamp: Indicates that the item will be considered invalid
   *     after this time, i.e. it will not be returned by get() unless
   *     $allow_invalid has been set to TRUE. When the item has expired, it may
   *     be permanently deleted by the garbage collector at any time.
   * @param array $tags
   *   An array of tags to be stored with the cache item. These should normally
   *   identify objects used to build the cache item, which should trigger
   *   cache invalidation when updated. For example if a cached item represents
   *   a node, both the node ID and the author's user ID might be passed in as
   *   tags. For example ['node:123', 'node:456', 'user:789'].
   */
  protected function cacheSet(string $cid, mixed $data, int $expire = Cache::PERMANENT, array $tags = []): void {
    \Drupal::cache('ai_assistant_api')->set($cid, $data, $expire, $tags);
  }

  /**
   * Returns data from the persistent cache.
   *
   * @param string $cid
   *   The cache ID of the data to retrieve.
   * @param bool $allow_invalid
   *   (optional) If TRUE, a cache item may be returned even if it is expired or
   *   has been invalidated. Such items may sometimes be preferred, if the
   *   alternative is recalculating the value stored in the cache, especially
   *   if another concurrent request is already recalculating the same value.
   *   The "valid" property of the returned object indicates whether the item is
   *   valid or not. Defaults to FALSE.
   *
   * @return object|false
   *   The cache item or FALSE on failure.
   */
  protected function cacheGet(string $cid, bool $allow_invalid = FALSE): object|false {
    return \Drupal::cache('ai_assistant_api')->get($cid, $allow_invalid);
  }

}
