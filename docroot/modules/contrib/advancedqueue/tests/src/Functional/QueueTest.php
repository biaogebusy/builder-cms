<?php

declare(strict_types=1);

namespace Drupal\Tests\advancedqueue\Functional;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
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
  public function testQueueCreation(): void {
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
  public function testQueueEditing(): void {
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
  public function testQueueDeletion(): void {
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

  /**
   * Tests the view provided by the module.
   */
  public function testQueueView(): void {
    \Drupal::service('module_installer')->install(['views', 'advancedqueue_test']);
    $this->drupalGet('admin/config/system/queues');
    $this->clickLink('List jobs');
    $this->assertSession()->pageTextContains('No jobs found');
    $queue = Queue::load('default');
    $job1 = Job::create('simple', ['test' => '1']);
    $job2 = Job::create('simple', ['test' => '2']);
    $queue->enqueueJobs([$job1, $job2]);
    $this->getSession()->reload();
    $this->assertSession()->pageTextNotContains('No jobs found');
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[0]')->check();
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[1]')->check();
    $this->assertSession()->fieldExists('action')->selectOption('delete');
    $this->assertSession()->buttonExists('Apply to selected items')->press();
    $this->assertSession()->pageTextContains('Are you sure you want to delete these jobs?');
    $this->assertSession()->buttonExists('Confirm')->press();
    $this->assertSession()->pageTextContains('Deleted 2 jobs');
    $this->assertSession()->pageTextContains('No jobs found');

    $queue->enqueueJobs([$job1, $job2]);
    $this->getSession()->reload();
    $this->assertSession()->pageTextNotContains('No jobs found');
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[0]')->check();
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[1]')->check();
    $this->assertSession()->fieldExists('action')->selectOption('release');
    $this->assertSession()->buttonExists('Apply to selected items')->press();
    $this->assertSession()->pageTextContains('Cannot execute Release on the job ID 4.');
    $this->assertSession()->pageTextContains('Cannot execute Release on the job ID 3.');

    // Set a job to processing so it can be released.
    $queue->getBackend()->claimJob();
    $this->getSession()->reload();
    $this->assertSession()->elementTextContains('css', '.views-form table', 'Processing');
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[0]')->check();
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[1]')->check();
    $this->assertSession()->fieldExists('action')->selectOption('release');
    $this->assertSession()->buttonExists('Apply to selected items')->press();
    $this->assertSession()->pageTextContains('Are you sure you want to release this job?');
    $this->assertSession()->pageTextContains('Cannot execute Release on the job ID 4.');
    $this->assertSession()->buttonExists('Confirm')->press();
    $this->assertSession()->elementTextNotContains('css', '.views-form table', 'Processing');
    $this->assertSession()->pageTextContains('Released 1 job');

    // Set a job to failed so it can be retried.
    $job = $queue->getBackend()->claimJob();
    $job->setState(Job::STATE_FAILURE);
    $queue->getBackend()->onFailure($job);
    $this->getSession()->reload();
    $this->assertSession()->elementTextContains('css', '.views-form table', 'Failure');
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[0]')->check();
    $this->assertSession()->fieldExists('advancedqueue_bulk_form[1]')->check();
    $this->assertSession()->fieldExists('action')->selectOption('retry');
    $this->assertSession()->buttonExists('Apply to selected items')->press();
    $this->assertSession()->pageTextContains('Are you sure you want to retry this job?');
    $this->assertSession()->pageTextContains('Cannot execute Retry on the job ID 4.');
    $this->assertSession()->buttonExists('Confirm')->press();
    $this->assertSession()->elementTextNotContains('css', '.views-form table', 'Failure');
    $this->assertSession()->pageTextContains('Retried 1 job');
    // Ensure the number of retries column is excluded from the display as the
    // number of retries is added on the state column.
    $this->assertSession()->pageTextContainsOnce('Number of retries: 1');
  }

  /**
   * Tests views integration.
   */
  public function testQueueViewsIntegration(): void {
    \Drupal::service('module_installer')->install(['views']);
    $queue = Queue::create([
      'id' => 'test',
      'label' => 'Test queue',
      'backend' => 'database',
      'processor' => QueueInterface::PROCESSOR_DAEMON,
      'processing_time' => 100,
    ]);
    $queue->save();
    $this->drupalGet('admin/config/system/queues');
    $this->assertSession()->pageTextContains('Test queue');
    $this->assertSession()->pageTextMatchesCount(2, '/List jobs/');
    $this->clickLink('List jobs');
    $this->assertSession()->pageTextContainsOnce('No jobs found');
    \Drupal::service('module_installer')->uninstall(['views']);
    $this->drupalGet('admin/config/system/queues');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test queue');
    $this->assertSession()->pageTextNotContains('List jobs');
  }

}
