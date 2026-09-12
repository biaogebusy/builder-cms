<?php

namespace Drupal\Tests\queue_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\queue_ui\QueueUIManager;
use Drupal\queue_ui_test\Queue\TestQueue;

/**
 * Tests the QueueUIManager.
 *
 * @group queue_ui
 * @runTestsInSeparateProcesses
 */
class QueueUIManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['queue_ui', 'queue_ui_test', 'system', 'user'];

  /**
   * The QueueUI manager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  protected $queueUIManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->queueUIManager = $this->container->get('plugin.manager.queue_ui');
  }

  /**
   * Tests finding a QueueUI plugin from a queue name.
   */
  public function testFromQueueName() {
    // 1. Test matching by short name (default DatabaseQueue).
    // By default, 'test_queue' should be a DatabaseQueue.
    $queue_ui = $this->queueUIManager->fromQueueName('test_queue');
    $this->assertInstanceOf('\Drupal\queue_ui\Plugin\QueueUI\DatabaseQueue', $queue_ui);

    // 2. Test matching by full class name using our test module's plugin.
    $test_queue = new TestQueue('test');
    $queue_factory = $this->createMock('\Drupal\Core\Queue\QueueFactory');
    $queue_factory->method('get')->willReturn($test_queue);

    // Create a new manager with the mocked service to avoid container state
    // issues.
    $manager = new QueueUIManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler'),
      $queue_factory
    );

    $queue_ui = $manager->fromQueueName('any_queue');
    $this->assertInstanceOf('\Drupal\queue_ui_test\Plugin\QueueUI\TestQueueUI', $queue_ui);

    // 3. Test with a queue that has no matching QueueUI plugin.
    $none_queue = $this->createMock('\Drupal\Core\Queue\QueueInterface');
    $queue_factory_none = $this->createMock('\Drupal\Core\Queue\QueueFactory');
    $queue_factory_none->method('get')->willReturn($none_queue);

    $manager_none = new QueueUIManager(
      $this->container->get('container.namespaces'),
      $this->container->get('cache.discovery'),
      $this->container->get('module_handler'),
      $queue_factory_none
    );

    $queue_ui = $manager_none->fromQueueName('none_queue');
    $this->assertFalse($queue_ui);
  }

  /**
   * Tests queueClassName method.
   */
  public function testQueueClassName() {
    $queue = $this->container->get('queue')->get('test_queue');
    $this->assertEquals('DatabaseQueue', $this->queueUIManager->queueClassName($queue));

    $test_queue = new TestQueue('test');
    $this->assertEquals('TestQueue', $this->queueUIManager->queueClassName($test_queue));
  }

}
