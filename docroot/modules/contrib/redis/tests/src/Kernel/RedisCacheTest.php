<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\ChainedFastBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\Core\Cache\GenericCacheBackendUnitTestBase;
use Drupal\redis\Cache\RedisBackend;
use Drupal\redis\RedisPrefixTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests Redis cache backend using GenericCacheBackendUnitTestBase.
 *
 * @group redis
 */
#[Group('redis')]
#[RunTestsInSeparateProcesses]
class RedisCacheTest extends GenericCacheBackendUnitTestBase {

  use RedisTestInterfaceTrait;
  use RedisPrefixTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'redis'];

  public function register(ContainerBuilder $container) {
    self::setUpSettings();
    parent::register($container);
    // Replace the default checksum service with the redis implementation.
    if ($container->has('redis.factory')) {
      $container->register('cache_tags.invalidator.checksum', 'Drupal\redis\Cache\RedisCacheTagsChecksum')
        ->addArgument(new Reference('redis.factory'))
        ->addTag('cache_tags_invalidator');
    }

    $time = new TestTime();
    $container->set('datetime.time', $time);
  }

  /**
   * Creates a new instance of PhpRedis cache backend.
   *
   * @return \Drupal\redis\Cache\RedisBackend
   *   A new PhpRedis cache backend.
   */
  protected function createCacheBackend($bin) {
    $cache = \Drupal::service('cache.backend.redis')->get($bin);
    return $cache;
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   *
   * @group legacy
   */
  #[IgnoreDeprecations]
  public function testInvalidateAll(): void {
    // This test is copied from the parent, as we need to undo the default
    // optimization for it to work and D12 will remove it.
    $this->setSetting('redis_invalidate_all_as_delete', FALSE);

    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $this->expectDeprecation('CacheBackendInterface::invalidateAll() is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead. See https://www.drupal.org/node/3500622');
    $backend_a->invalidateAll();

    $this->assertFalse($backend_a->get('test1'), 'First key has been invalidated.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been invalidated.');
    $this->assertNotEmpty($backend_b->get('test3'), 'Item in other bin is preserved.');
    $this->assertNotEmpty($backend_a->get('test1', TRUE), 'First key has not been deleted.');
    $this->assertNotEmpty($backend_a->get('test2', TRUE), 'Second key has not been deleted.');
  }

  /**
   * Tests Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   *
   * @group legacy
   */
  public function testInvalidateAllOptimized(): void {
    $this->setSetting('redis_invalidate_all_as_delete', TRUE);
    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $backend_a->invalidateAll();

    $this->assertFalse($backend_a->get('test1'), 'First key has been invalidated.');
    $this->assertFalse($backend_a->get('test2'), 'Second key has been invalidated.');
    $this->assertNotEmpty($backend_b->get('test3'), 'Item in other bin is preserved.');

    // Keys can also no longer be retrieved when allowing invalid caches to be
    // returned.
    $this->assertEmpty($backend_a->get('test1', TRUE), 'First key has been deleted.');
    $this->assertEmpty($backend_a->get('test2', TRUE), 'Second key has been deleted.');
  }

  /**
   * Tests the expiration offset.
   */
  public function testExpirationOffset(): void {
    $backend = $this->getCacheBackend();

    // Set both expiring and permanent keys.
    $backend->set('test1', 1, Cache::PERMANENT);
    $backend->set('test2', 2, \Drupal::time()->getRequestTime() + 1);

    // After waiting 2 seconds, the temporary item has expired, for BC, the
    // Redis TTL is set with a default offset, so the item can still be found
    // with allow invalid.
    $this->wait(2);
    $this->assertNotEmpty($backend->get('test1'), 'First key is valid.');
    $this->assertFalse($backend->get('test2'), 'Second key is no longer valid.');
    $this->assertNotEmpty($backend->get('test2', TRUE), 'Second key is still available with allow_invalid.');

    // Set the offset to 0, which sets Redis TTL to the same as expire, so
    // allow invalid no longer finds the item either.
    $this->setSetting('redis_ttl_offset', 0);
    $backend->set('test3', 3, \Drupal::time()->getRequestTime() + 2);
    $this->assertNotEmpty($backend->get('test3'), 'Third key is still valid.');
    $this->wait(4);
    $this->assertFalse($backend->get('test3', TRUE), 'Third key is no longer available.');

    // Recommended configuration. Set a given offset, which allows for the allow
    // invalid parameter to still work for the given time.
    $this->setSetting('redis_ttl_offset', 3);
    $backend->set('test4', 4, \Drupal::time()->getRequestTime() + 2);
    $this->wait(4);
    $this->assertFalse($backend->get('test4'), 'Fourth key is no longer valid.');
    $this->assertNotEmpty($backend->get('test4', TRUE), 'Fourth key is still available with allow_invalid.');
    $this->wait(4);
    $this->assertFalse($backend->get('test4', TRUE), 'Fourth key is no longer available.');
  }

  /**
   * Tests setPermTtl()
   */
  public function testSetPermTtl(): void {
    // Test different supported settings.
    $this->setSetting('redis_perm_ttl_seconds', 3600);
    $this->setSetting('redis_perm_ttl_permanent', Cache::PERMANENT);
    $this->setSetting('redis_perm_ttl_datestring', '1 day + 1 hour + 1 minute + 1 second');
    $this->setSetting('redis.settings', [
      'perm_ttl_legacy' => 900,
      // Not used, the new and documented key is preferred.
      'perm_ttl_seconds' => 1,
   ]);

    $backend = $this->getCacheBackend();
    $this->assertEquals(RedisBackend::LIFETIME_PERM_DEFAULT, $backend->getPermTtl());

    $backend = $this->getCacheBackend('seconds');
    $this->assertEquals(3600, $backend->getPermTtl());

    $backend = $this->getCacheBackend('datestring');
    $this->assertEquals(90061, $backend->getPermTtl());

    $backend = $this->getCacheBackend('legacy');
    $this->assertEquals(900, $backend->getPermTtl());

    // Support permanent explicitly and on the default bins.
    $backend = $this->getCacheBackend('permanent');
    $this->assertEquals(Cache::PERMANENT, $backend->getPermTtl());
    $backend = $this->getCacheBackend('bootstrap');
    $this->assertEquals(Cache::PERMANENT, $backend->getPermTtl());
  }

  /**
   * Ensures that cache items written to a permanent bin do not have a TTL.
   *
   * This test requires Redis/Valkey 7.0+ to assert the expiration time.
   */
  public function testCacheItemTtl(): void {

    $bootstrap_backend = $this->getCacheBackend('bootstrap');
    $default_backend = $this->getCacheBackend('default');

    // Write a permanent and an expiring item into both bins.
    $bootstrap_backend->set('permanent_cid', 'permanent');
    $default_backend->set('permanent_cid', 'permanent');
    $expire = \Drupal::time()->getRequestTime() + 10;
    $bootstrap_backend->set('expiring_cid', 'will_expire', $expire);
    $default_backend->set('expiring_cid', 'will_expire', $expire);

    $bootstrap_prefix = $this->getPrefix() . ':bootstrap:';
    $default_prefix = $this->getPrefix() . ':default:';

    /** @var \Drupal\redis\ClientInterface $client */
    $client = \Drupal::service('redis.factory')->getClient();

    // Directly assert the expected expiration times.
    $this->assertEquals(-1, $client->rawCommand('EXPIRETIME', $bootstrap_prefix . 'permanent_cid'));
    $this->assertGreaterThan(\Drupal::time()->getRequestTime() + RedisBackend::LIFETIME_PERM_DEFAULT - 5, $client->rawCommand('EXPIRETIME', $default_prefix . 'permanent_cid'));

    // For an expiring item below the default TTL, both bins behave the same
    // way, include the TTL offset and account for minor variation.
    $this->assertGreaterThan($expire + 3600 - 2, $client->rawCommand('EXPIRETIME', $bootstrap_prefix . 'expiring_cid'));
    $this->assertLessThan($expire + 3600 + 5, $client->rawCommand('EXPIRETIME', $bootstrap_prefix . 'expiring_cid'));
    $this->assertGreaterThan($expire + 3600 - 5, $client->rawCommand('EXPIRETIME', $default_prefix . 'expiring_cid'));
    $this->assertLessThan($expire + 3600 + 5, $client->rawCommand('EXPIRETIME', $default_prefix . 'expiring_cid'));
  }

  /**
   * Wait a given amount of time.
   *
   * Since this tests both the internal expiration given by the time
   * service as well as the external redis server, this needs to both
   * update the time service as well as really sleep for the given time.
   *
   * @param int $seconds
   *   The amount of seconds to wait.
   */
  public function wait(int $seconds): void {
    // In kernel tests, time does not have a request injected and falls back
    // to $_SERVER['REQUEST_TIME'], update that.
    TestTime::$offset += $seconds;
    sleep($seconds);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheBackend($bin = NULL): RedisBackend|ChainedFastBackend {
    return parent::getCacheBackend($bin);
  }

}

/**
 * A test-only implementation of the time service.
 */
class TestTime extends Time {

  /**
   * An offset to add to the request time.
   */
  public static int $offset = 0;

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return parent::getRequestTime() + static::$offset;
  }

}
