<?php

namespace Drupal\Tests\advancedqueue\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\advancedqueue\Processor
 * @group advancedqueue
 */
class ProcessorTest extends KernelTestBase {

  /**
   * The test queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * The processor being tested.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected $processor;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'advancedqueue',
    'advancedqueue_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('advancedqueue', ['advancedqueue']);

    $this->queue = Queue::create([
      'id' => 'test',
      'label' => 'Test queue',
      'backend' => 'database',
      'backend_configuration' => [
        'lease_time' => 5,
      ],
    ]);
    $this->queue->save();

    $this->processor = $this->container->get('advancedqueue.processor');
  }

  /**
   * @covers ::processQueue
   * @covers ::processJob
   */
  public function testProcessor() {
    $first_job = Job::create('simple', ['test' => '1']);
    $second_job = Job::create('flexible',
      ['expected_state' => Job::STATE_SUCCESS, 'expected_message' => 'Done!']);
    $third_job = Job::create('flexible', ['expected_exception' => 'DB down!']);
    $fourth_job = Job::create('flexible',
      ['expected_state' => Job::STATE_FAILURE, 'expected_message' => 'Failed!']);

    $this->queue->enqueueJob($first_job);
    $this->queue->enqueueJob($second_job);
    $this->queue->enqueueJob($third_job);
    $this->queue->enqueueJob($fourth_job);

    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(4, $num_processed);

    /** @var \Drupal\Core\Database\Connection $connection */
    $connection = $this->container->get('database');
    $raw_jobs = $connection->query('SELECT job_id, state, message FROM {advancedqueue}')->fetchAllAssoc('job_id', \PDO::FETCH_ASSOC);
    $this->assertEquals([
      'job_id' => '1',
      'state' => Job::STATE_SUCCESS,
      'message' => NULL,
    ], $raw_jobs[1]);
    $this->assertEquals([
      'job_id' => '2',
      'state' => Job::STATE_SUCCESS,
      'message' => 'Done!',
    ], $raw_jobs[2]);
    $this->assertEquals([
      'job_id' => '3',
      'state' => Job::STATE_FAILURE,
      'message' => 'DB down!',
    ], $raw_jobs[3]);
    $this->assertEquals([
      'job_id' => '4',
      'state' => Job::STATE_FAILURE,
      'message' => 'Failed!',
    ], $raw_jobs[4]);
  }

  /**
   * @covers ::processQueue
   *
   * @dataProvider retryJobProvider
   */
  public function testRetry(Job $job) {
    $this->queue->setProcessingTime(2);
    $this->queue->enqueueJob($job);

    // Confirm that the job has been requeued.
    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(1, $num_processed);
    $counts = $this->queue->getBackend()->countJobs();
    $this->assertEquals([Job::STATE_QUEUED => 1], array_filter($counts));

    // Confirm that the job is skipped due to $retry_delay.
    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(0, $num_processed);

    // Confirm that the job was re-processed, and left after the $retry_limit.
    sleep(5);
    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(1, $num_processed);
    $counts = $this->queue->getBackend()->countJobs();
    $this->assertEquals([Job::STATE_FAILURE => 1], array_filter($counts));

    /** @var \Drupal\Core\Database\Connection $connection */
    $connection = $this->container->get('database');
    $raw_jobs = $connection->query('SELECT job_id, state, num_retries FROM {advancedqueue}')->fetchAllAssoc('job_id', \PDO::FETCH_ASSOC);
    $this->assertEquals([
      'job_id' => '1',
      'state' => Job::STATE_FAILURE,
      'num_retries' => 1,
    ], $raw_jobs[1]);
  }

  /**
   * Data provider for ::testRetry.
   *
   * @return array
   *   A list of testRetry function arguments.
   */
  public function retryJobProvider() {
    // The first job has job-type-level retry parameters.
    // The second job has result-level retry parameters.
    $first_job = Job::create('retry', ['test' => '1']);
    $second_job = Job::create('flexible', [
      'expected_state' => Job::STATE_FAILURE,
      'expected_message' => '',
      'max_retries' => '1',
      'retry_delay' => 5,
    ]);

    return [[$first_job], [$second_job]];
  }

  /**
   * @covers ::processQueue
   */
  public function testTimeLimit() {
    $this->queue->setProcessingTime(2);
    $this->queue->save();

    $first_job = Job::create('sleepy', ['test' => '1']);
    $second_job = Job::create('sleepy', ['test' => '1']);
    $third_job = Job::create('sleepy', ['test' => '1']);

    $this->queue->enqueueJob($first_job);
    $this->queue->enqueueJob($second_job);
    $this->queue->enqueueJob($third_job);

    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(2, $num_processed);
    $counts = $this->queue->getBackend()->countJobs();
    $this->assertEquals([Job::STATE_QUEUED => 1, Job::STATE_SUCCESS => 2], array_filter($counts));

    $num_processed = $this->processor->processQueue($this->queue);
    $this->assertEquals(1, $num_processed);
    $counts = $this->queue->getBackend()->countJobs();
    $this->assertEquals([Job::STATE_SUCCESS => 3], array_filter($counts));
  }

}
