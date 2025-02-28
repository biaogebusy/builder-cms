<?php

namespace Drupal\memcache\Invalidator;

/**
 * Class TimestampInvalidatorBase.
 *
 * Base class for timestamp-based tag invalidation.
 *
 * @package Drupal\memcache\Invalidator
 */
abstract class TimestampInvalidatorBase implements TimestampInvalidatorInterface {

  /**
   * Allowed timestamp slop.
   *
   * @var float
   */
  protected $tolerance;

  /**
   * TimestampInvalidatorBase constructor.
   *
   * @param float $tolerance
   *   Allowed clock skew between servers, in decimal seconds.
   */
  public function __construct($tolerance = 0.001) {
    $this->tolerance = $tolerance;
  }

  /**
   * Mark a tag as outdated.
   *
   * @param string $tag
   *   Tag to mark as outdated.
   *
   * @return float
   *   New timestamp for tag.
   */
  protected function markAsOutdated($tag) {
    $now = $this->getCurrentTimestamp($this->tolerance);
    $current = $this->getLastInvalidationTimestamp($tag);
    if ($now > $current) {
      $this->writeTimestamp($tag, $now);
      return $now;
    }
    else {
      return $current;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTimestamp($offset = 0.0) {
    // @todo Eventually we might want to use a time service instead of microtime().
    // Unfortunately, TimeInterface needs a request object and we don't have
    // that in the bootstrap container.
    return round(microtime(TRUE) + $offset, 3);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function invalidateTimestamp($tag);

  /**
   * {@inheritdoc}
   */
  abstract public function getLastInvalidationTimestamps(array $tags);

  /**
   * Write an updated timestamp for a tag to the backend.
   *
   * @param string $tag
   *   Tag to write.
   * @param float $timestamp
   *   New timestamp to write.
   *
   * @return bool
   *   Success or failure from backend.
   */
  abstract protected function writeTimestamp($tag, $timestamp);

}
