<?php

namespace Drupal\memcache\Invalidator;

/**
 * Interface TimestampInvalidatorInterface.
 *
 * Defines an interface for timestamp-based tag invalidation.
 *
 * @package Drupal\memcache\Invalidator
 */
interface TimestampInvalidatorInterface {

  /**
   * Invalidate the timestamp of a tag.
   *
   * @param string $tag
   *   Tag to invalidate.
   *
   * @return float
   *   New timestamp of tag.
   */
  public function invalidateTimestamp($tag);

  /**
   * Get the last invalidation timestamp of a tag.
   *
   * @param string $tag
   *   Tag to check.
   *
   * @return float
   *   The last invalidation timestamp of the tag.
   */
  public function getLastInvalidationTimestamp($tag);

  /**
   * Get the last invalidation timestamps of a set of tags.
   *
   * @param array $tags
   *   Array of tags to check (keys are ignored.)
   *
   * @return array|bool
   *   The last invalidation timestamps on file, or FALSE on failure.
   */
  public function getLastInvalidationTimestamps(array $tags);

  /**
   * Get the current timestamp, optionally offset by a number.
   *
   * The standard granularity of the resulting timestamp is three decimal
   * places, (1 millisecond).
   *
   * @param float $offset
   *   Offset to apply to timestamp before rounding.
   *
   * @return float
   *   Current timestamp in decimal seconds.
   */
  public function getCurrentTimestamp($offset = 0.0);

}
