<?php

namespace Drupal\memcache\Invalidator;

use Drupal\memcache\Driver\MemcacheDriverFactory;

/**
 * Class MemcacheTimestampInvalidator.
 */
class MemcacheTimestampInvalidator extends TimestampInvalidatorBase {

  /**
   * A Memcache object.
   *
   * @var \Drupal\memcache\DrupalMemcacheInterface
   */
  protected $memcache;

  /**
   * MemcacheTimestampInvalidator constructor.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $memcache_factory
   *   Factory class for creation of Memcache objects.
   * @param string $bin
   *   Memcache bin to store the timestamps in.
   * @param float $tolerance
   *   Allowed clock skew between servers, in decimal seconds.
   */
  public function __construct(MemcacheDriverFactory $memcache_factory, $bin, $tolerance = 0.001) {
    parent::__construct($tolerance);
    $this->memcache = $memcache_factory->get($bin);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTimestamp($tag) {
    return $this->markAsOutdated($tag);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInvalidationTimestamp($tag) {
    return $this->memcache->get($tag);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInvalidationTimestamps(array $tags) {
    return $this->memcache->getMulti($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function writeTimestamp($tag, $timestamp) {
    return $this->memcache->set($tag, $timestamp);
  }

}
