<?php

namespace Drupal\Tests\redis\Kernel;

use Drupal\KernelTests\Core\Queue\QueueTest as CoreQueueTest;
use Drupal\redis\Queue\RedisQueue;
use Drupal\redis\Queue\ReliableRedisQueue;
use Drupal\Tests\redis\Traits\RedisTestInterfaceTrait;

/**
 * Tests the Redis queue functions.
 *
 * @group redis
 */
class RedisQueueTest extends CoreQueueTest {

  use RedisTestInterfaceTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['redis'];

  /**
   * Tests Redis non-blocking queue.
   */
  public function testRedisNonBlockingQueue(): void {
    self::setUpSettings();
    $client_factory = \Drupal::service('redis.factory');
    $settings = ['reserve_timeout' => NULL];

    $queue1 = new RedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    $queue2 = new RedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->runQueueTest($queue1, $queue2);
    $queue1->deleteQueue();
    $queue2->deleteQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue1 */
    $queue1 = new ReliableRedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue2 */
    $queue2 = new ReliableRedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->runQueueTest($queue1, $queue2);
  }

  /**
   * Tests Redis blocking queue.
   */
  public function testRedisBlockingQueue(): void {
    self::setUpSettings();
    // Create two queues.
    $client_factory = \Drupal::service('redis.factory');
    $settings = ['reserve_timeout' => 30];

    /** @var \Drupal\Core\Queue\QueueInterface $queue1 */
    $queue1 = new RedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue1->createQueue();

    /** @var \Drupal\Core\Queue\QueueInterface $queue2 */
    $queue2 = new RedisQueue($this->randomMachineName(), $settings, $client_factory->getClient());
    $queue2->createQueue();

    $this->runQueueTest($queue1, $queue2);
  }

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testSystemQueue().
   *
   * We override tests from core class we extend to prevent them from running.
   */
  public function testSystemQueue(): void {
    $this->markTestSkipped();
  }

  /**
   * Overrides \Drupal\system\Tests\Queue\QueueTestQueueTest::testMemoryQueue().
   *
   * We override tests from core class we extend to prevent them from running.
   */
  public function testMemoryQueue(): void {
    $this->markTestSkipped();
  }

}
