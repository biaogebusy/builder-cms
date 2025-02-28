<?php

namespace Drupal\memcache\Cache;

use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;

/**
 * Cache tags invalidations checksum implementation by timestamp invalidation.
 */
class TimestampCacheTagsChecksum implements CacheTagsChecksumInterface, CacheTagsInvalidatorInterface {

  /**
   * The timestamp invalidator object.
   *
   * @var \Drupal\memcache\Invalidator\TimestampInvalidatorInterface
   */
  protected $invalidator;

  /**
   * Contains already loaded cache invalidations from the backend.
   *
   * @var array
   */
  protected $tagCache = [];

  /**
   * A list of tags that have already been invalidated in this request.
   *
   * Used to prevent the invalidation of the same cache tag multiple times.
   *
   * @var array
   */
  protected $invalidatedTags = [];

  /**
   * Constructs a TimestampCacheTagsChecksum object.
   *
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $invalidator
   *   The timestamp invalidator object.
   */
  public function __construct(TimestampInvalidatorInterface $invalidator) {
    $this->invalidator = $invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    foreach ($tags as $tag) {
      // @todo Revisit this behavior and determine a better way to handle.
      // Only invalidate tags once per request unless they are written again.
      if (isset($this->invalidatedTags[$tag])) {
        continue;
      }
      $this->invalidatedTags[$tag] = TRUE;
      $this->tagCache[$tag] = $this->invalidator->invalidateTimestamp($tag);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentChecksum(array $tags) {
    // @todo Revisit the invalidatedTags hack.
    // Remove tags that were already invalidated during this request from the
    // static caches so that another invalidation can occur later in the same
    // request. Without that, written cache items would not be invalidated
    // correctly.
    foreach ($tags as $tag) {
      unset($this->invalidatedTags[$tag]);
    }
    // Taking the minimum of the current timestamp and the checksum is used to
    // ensure that items that are not valid yet are identified properly as not
    // valid. The checksum will change continuously until the item is valid,
    // at which point the checksum will match and freeze at that value.
    return min($this->invalidator->getCurrentTimestamp(), $this->calculateChecksum($tags));
  }

  /**
   * {@inheritdoc}
   */
  public function isValid($checksum, array $tags) {
    if (empty($tags)) {
      // If there weren't any tags, the checksum should always be 0 or FALSE.
      return $checksum == 0;
    }
    return $checksum == $this->calculateChecksum($tags);
  }

  /**
   * Calculates the current checksum for a given set of tags.
   *
   * @param array $tags
   *   The array of tags to calculate the checksum for.
   *
   * @return int
   *   The calculated checksum.
   */
  protected function calculateChecksum(array $tags) {

    $query_tags = array_diff($tags, array_keys($this->tagCache));
    if ($query_tags) {
      $backend_tags = $this->invalidator->getLastInvalidationTimestamps($query_tags);
      $this->tagCache += $backend_tags;
      $invalid = array_diff($query_tags, array_keys($backend_tags));
      if (!empty($invalid)) {
        // Invalidate any missing tags now. This is necessary because we cannot
        // zero-optimize our tag list -- we can't tell the difference between
        // a tag that has never been invalidated and a tag that was
        // garbage-collected by the backend!
        //
        // This behavioral difference is the main change that allows us to use
        // an unreliable backend to track cache tag invalidation.
        //
        // Invalidating the tag will cause it to start being tracked, so it can
        // be matched against the checksums stored on items.
        // All items cached after that point with the tag will end up with
        // a valid checksum, and all items cached before that point with the tag
        // will have an invalid checksum, because missing invalidations will
        // keep moving forward in time as they get garbage collected and are
        // re-invalidated.
        //
        // The main effect of all this is that a tag going missing
        // will automatically cause the cache items tagged with it to no longer
        // have the correct checksum.
        foreach ($invalid as $invalid_tag) {
          $this->invalidator->invalidateTimestamp($invalid_tag);
        }
      }
    }

    // The checksum is equal to the *most recent* invalidation of an applicable
    // tag. If the item is untagged, the checksum is always 0.
    return max([0] + array_intersect_key($this->tagCache, array_flip($tags)));
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->tagCache = [];
    $this->invalidatedTags = [];
  }

}
