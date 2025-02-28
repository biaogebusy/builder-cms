<?php

namespace Drupal\Tests\advancedqueue\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the queue UI.
 *
 * @group advancedqueue
 */
class QueueTest extends BrowserTestBase {

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'advancedqueue',
    'block',
    'views',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('page_title_block');

    $this->adminUser = $this->drupalCreateUser(['administer advancedqueue']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests creating a queue.
   */
  public function testQueueCreation() {
    $this->drupalGet('admin/config/system/queues');
    $this->getSession()->getPage()->clickLink('Add queue');
    $this->assertSession()->addressEquals('admin/config/system/queues/add');

    $values = [
      'label' => 'Test',
      'configuration[database][lease_time]' => '200',
      'processor' => QueueInterface::PROCESSOR_DAEMON,
      'processing_time' => '100',
      // Setting the 'id' can fail if focus switches to another field.
      // This is a bug in the machine name JS that can be reproduced manually.
      'id' => 'test',
    ];
    $this->submitForm($values, 'Save');
    $this->assertSession()->addressEquals('admin/config/system/queues');
    $this->assertSession()->responseContains('Test');

    $queue = Queue::load('test');
    $this->assertEquals('test', $queue->id());
    $this->assertEquals('Test', $queue->label());
    $this->assertEquals('database', $queue->getBackendId());
    $this->assertEquals(['lease_time' => 200], $queue->getBackendConfiguration());
    $this->assertEquals($queue->getBackendConfiguration(), $queue->getBackend()->getConfiguration());
    $this->assertEquals(QueueInterface::PROCESSOR_DAEMON, $queue->getProcessor());
    $this->assertEquals(100, $queue->getProcessingTime());
    $this->assertFalse($queue->isLocked());
  }

  /**
   * Tests editing a queue.
   */
  public function testQueueEditing() {
    $queue = Queue::create([
      'id' => 'test',
      'label' => 'Test',
      'backend' => 'database',
      'processor' => QueueInterface::PROCESSOR_DAEMON,
      'processing_time' => 100,
    ]);
    $queue->save();

    $this->drupalGet('admin/config/system/queues/manage/' . $queue->id());
    $this->submitForm([
      'label' => 'Test (Modified)',
      'configuration[database][lease_time]' => '202',
      'processor' => QueueInterface::PROCESSOR_CRON,
      'processing_time' => '120',
    ], 'Save');

    \Drupal::entityTypeManager()->getStorage('advancedqueue_queue')->resetCache();
    $queue = Queue::load('test');
    $this->assertEquals('test', $queue->id());
    $this->assertEquals('Test (Modified)', $queue->label());
    $this->assertEquals('database', $queue->getBackendId());
    $this->assertEquals(['lease_time' => 202], $queue->getBackendConfiguration());
    $this->assertEquals($queue->getBackendConfiguration(), $queue->getBackend()->getConfiguration());
    $this->assertEquals(QueueInterface::PROCESSOR_CRON, $queue->getProcessor());
    $this->assertEquals(120, $queue->getProcessingTime());
    $this->assertFalse($queue->isLocked());
  }

  /**
   * Tests deleting a queue.
   */
  public function testQueueDeletion() {
    $queue = Queue::create([
      'id' => 'test',
      'label' => 'Test',
      'backend' => 'database',
      'processor' => QueueInterface::PROCESSOR_DAEMON,
      'processing_time' => 100,
    ]);
    $queue->save();
    $this->drupalGet('admin/config/system/queues/manage/' . $queue->id() . '/delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->addressEquals('admin/config/system/queues');

    $queue_exists = (bool) Queue::load('test');
    $this->assertEmpty($queue_exists, 'The queue has been deleted from the database.');
  }

}
