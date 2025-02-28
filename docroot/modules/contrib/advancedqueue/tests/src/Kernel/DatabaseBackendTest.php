<?php

namespace Drupal\Tests\advancedqueue\Kernel;

use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\Database
 * @group advancedqueue
 */
class DatabaseBackendTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * The first tested queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $firstQueue;

  /**
   * The second tested queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $secondQueue;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'advancedqueue',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('advancedqueue', ['advancedqueue']);
    // Override the current time to control job timestamps.
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn(635814000);
    $this->container->set('datetime.time', $mock_time->reveal());

    $this->firstQueue = Queue::create([
      'id' => 'first_queue',
      'label' => 'First queue',
      'backend' => 'database',
      'backend_configuration' => [
        'lease_time' => 5,
      ],
    ]);
    $this->firstQueue->save();

    $this->secondQueue = Queue::create([
      'id' => 'second_queue',
      'label' => 'Second queue',
      'backend' => 'database',
      'backend_configuration' => [
        'lease_time' => 5,
      ],
    ]);
    $this->secondQueue->save();
  }

  /**
   * @covers ::deleteQueue
   * @covers ::countJobs
   * @covers ::enqueueJob
   * @covers ::enqueueJobs
   * @covers ::claimJob
   * @covers ::onSuccess
   * @covers ::onFailure
   * @covers ::deleteJob
   */
  public function testQueue() {
    $first_job = Job::create('simple', ['test' => '1']);
    $second_job = Job::create('simple', ['test' => '2']);
    $third_job = Job::create('simple', ['test' => '3']);
    $fourth_job = Job::create('simple', ['test' => '4']);

    $this->firstQueue->getBackend()->enqueueJobs([$first_job, $third_job]);

    // Confirm that the other needed fields have been populated.
    $this->assertQueuedJob(1, 'first_queue', 0, $first_job);
    $this->assertQueuedJob(2, 'first_queue', 0, $third_job);

    // Confirm that the queue now contains two jobs.
    $counts = $this->firstQueue->getBackend()->countJobs();
    $this->assertEquals([Job::STATE_QUEUED => 2], array_filter($counts));

    // Update the jobs to match how they'll look when claimed.
    $first_job->setState(Job::STATE_PROCESSING);
    $first_job->setExpiresTime(635814000 + 5);
    $third_job->setExpiresTime(635814000 + 5);
    $third_job->setState(Job::STATE_PROCESSING);

    // Confirm that the jobs are returned in the correct order (FIFO).
    $first_claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($first_job, $first_claimed_job);

    $third_claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($third_job, $third_claimed_job);

    $this->assertNull($this->firstQueue->getBackend()->claimJob());

    $this->firstQueue->getBackend()->enqueueJobs([$first_job, $third_job]);
    $this->secondQueue->getBackend()->enqueueJob($second_job);
    $this->secondQueue->getBackend()->enqueueJob($fourth_job);

    // Confirm that the other needed fields have been populated.
    $this->assertQueuedJob(5, 'second_queue', 0, $second_job);
    $this->assertQueuedJob(6, 'second_queue', 0, $fourth_job);

    // Update the jobs to match how they'll look when claimed.
    $second_job->setState(Job::STATE_PROCESSING);
    $second_job->setExpiresTime(635814000 + 5);
    $fourth_job->setExpiresTime(635814000 + 5);
    $fourth_job->setState(Job::STATE_PROCESSING);

    // Confirm that deleting the job works.
    $this->secondQueue->getBackend()->deleteJob($second_job->getId());
    $fourth_claimed_job = $this->secondQueue->getBackend()->claimJob();
    $this->assertEquals($fourth_job, $fourth_claimed_job);

    // Confirm fail -> retry -> success.
    $fourth_job->setState(Job::STATE_FAILURE);
    $this->secondQueue->getBackend()->onFailure($fourth_job);
    $this->assertEquals(635814000, $fourth_job->getProcessedTime());
    $this->assertEmpty($fourth_job->getExpiresTime());

    $this->secondQueue->getBackend()->retryJob($fourth_job, 9);
    $this->assertEquals(Job::STATE_QUEUED, $fourth_job->getState());
    $this->assertEquals(1, $fourth_job->getNumRetries());
    $this->assertEquals(635814000 + 9, $fourth_job->getAvailableTime());
    $this->assertEmpty($fourth_job->getExpiresTime());

    $this->rewindTime(635814010);
    $fourth_job->setState(Job::STATE_PROCESSING);
    $fourth_job->setExpiresTime(635814010 + 5);
    $fourth_claimed_job = $this->secondQueue->getBackend()->claimJob();
    $this->assertEquals($fourth_job, $fourth_claimed_job);

    $fourth_job->setState(Job::STATE_SUCCESS);
    $this->secondQueue->getBackend()->onSuccess($fourth_job);
    $this->assertEquals(635814010, $fourth_job->getProcessedTime());
    $this->assertEmpty($fourth_job->getExpiresTime());

    // Confirm updated counts.
    $this->secondQueue->getBackend()->enqueueJob($first_job);
    $this->secondQueue->getBackend()->enqueueJob($second_job);
    $counts = $this->secondQueue->getBackend()->countJobs();
    $this->assertEquals([
      Job::STATE_QUEUED => 2,
      Job::STATE_PROCESSING => 0,
      Job::STATE_SUCCESS => 1,
      Job::STATE_FAILURE => 0,
    ], $counts);

    // Confirm that deleting the queue removes the jobs.
    $this->firstQueue->getBackend()->deleteQueue();
    $this->assertNull($this->firstQueue->getBackend()->claimJob());
  }

  /**
   * @covers ::enqueueJob
   * @covers ::claimJob
   */
  public function testFutureQueue() {
    $first_job = Job::create('simple', ['test' => '1']);
    $second_job = Job::create('simple', ['test' => '2']);

    $this->firstQueue->getBackend()->enqueueJob($first_job, 5);
    $this->firstQueue->getBackend()->enqueueJob($second_job);
    $this->assertQueuedJob(1, 'first_queue', 5, $first_job);
    $this->assertQueuedJob(2, 'first_queue', 0, $second_job);

    // Update the job to match how it will look when claimed.
    $second_job->setState(Job::STATE_PROCESSING);
    $second_job->setExpiresTime(635814000 + 5);

    // Confirm that the first job isn't available because of the delay.
    $second_claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($second_job, $second_claimed_job);
    $this->assertNull($this->firstQueue->getBackend()->claimJob());

    // Confirm that rewinding time makes the first job available.
    $this->rewindTime(635814010);
    $first_job->setState(Job::STATE_PROCESSING);
    $first_job->setExpiresTime(635814010 + 5);
    $first_claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($first_job, $first_claimed_job);
  }

  /**
   * @covers ::cleanupQueue
   */
  public function testQueueCleanup() {
    $job = Job::create('simple', ['test' => '1']);
    $this->firstQueue->getBackend()->enqueueJob($job);
    // Update the job to match how it will look when claimed.
    $job->setState(Job::STATE_PROCESSING);
    $job->setExpiresTime(635814000 + 5);

    $claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($job, $claimed_job);

    $this->rewindTime(635814000 + 6);
    $this->assertNull($this->firstQueue->getBackend()->claimJob());

    // Running cleanup should expire the lease, making it possible to claim
    // the job for processing again.
    $this->firstQueue->getBackend()->cleanupQueue();
    $job->setExpiresTime(635814000 + 6 + 5);
    $claimed_job = $this->firstQueue->getBackend()->claimJob();
    $this->assertEquals($job, $claimed_job);
  }

  /**
   * @covers ::loadJob
   */
  public function testLoadJob() {
    $job = Job::create('simple', ['test' => '1']);
    $this->firstQueue->getBackend()->enqueueJob($job);
    $claimed_job = $this->firstQueue->getBackend()->claimJob();
    $loaded_job = $this->firstQueue->getBackend()->loadJob($claimed_job->getId());
    $this->assertEquals($loaded_job, $claimed_job);
  }

  /**
   * Changes the current time.
   *
   * @param int $new_time
   *   The new time.
   */
  protected function rewindTime($new_time) {
    $mock_time = $this->prophesize(TimeInterface::class);
    $mock_time->getCurrentTime()->willReturn($new_time);
    $this->container->set('datetime.time', $mock_time->reveal());

    // Reload the queues so that their backends get the updated service.
    $storage = $this->container->get('entity_type.manager')->getStorage('advancedqueue_queue');
    $storage->resetCache(['first_queue', 'second_queue']);
    $this->firstQueue = $storage->load('first_queue');
    $this->secondQueue = $storage->load('second_queue');
  }

  /**
   * Asserts that the queued job has the correct data.
   *
   * @param string $expected_id
   *   The expected job ID.
   * @param string $expected_queue_id
   *   The expected queue ID.
   * @param int $expected_delay
   *   The expected delay.
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   */
  protected function assertQueuedJob($expected_id, $expected_queue_id, $expected_delay, Job $job) {
    $this->assertEquals($expected_id, $job->getId());
    $this->assertEquals($expected_queue_id, $job->getQueueId());
    $this->assertEquals(Job::STATE_QUEUED, $job->getState());
    $this->assertEquals(635814000 + $expected_delay, $job->getAvailableTime());
  }

}
