<?php

namespace Drupal\memcache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\memcache\Driver\MemcacheDriverFactory;
use Drupal\memcache\Invalidator\TimestampInvalidatorInterface;

/**
 * Class MemcacheBackendFactory.
 */
class MemcacheBackendFactory implements CacheFactoryInterface {

  /**
   * The memcache factory object.
   *
   * @var \Drupal\memcache\Driver\MemcacheDriverFactory
   */
  protected $memcacheFactory;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The timestamp invalidation provider.
   *
   * @var \Drupal\memcache\Invalidator\TimestampInvalidatorInterface
   */
  protected $timestampInvalidator;

  /**
   * Constructs the MemcacheBackendFactory object.
   *
   * @param \Drupal\memcache\Driver\MemcacheDriverFactory $memcache_factory
   *   The memcache factory object.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   *   The cache tags checksum provider.
   * @param \Drupal\memcache\Invalidator\TimestampInvalidatorInterface $timestamp_invalidator
   *   The timestamp invalidation provider.
   */
  public function __construct(MemcacheDriverFactory $memcache_factory, CacheTagsChecksumInterface $checksum_provider, TimestampInvalidatorInterface $timestamp_invalidator) {
    $this->memcacheFactory = $memcache_factory;
    $this->checksumProvider = $checksum_provider;
    $this->timestampInvalidator = $timestamp_invalidator;
  }

  /**
   * Gets MemcacheBackend for the specified cache bin.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\memcache\MemcacheBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new MemcacheBackend(
      $bin,
      $this->memcacheFactory->get($bin),
      $this->checksumProvider,
      $this->timestampInvalidator
    );
  }

}
