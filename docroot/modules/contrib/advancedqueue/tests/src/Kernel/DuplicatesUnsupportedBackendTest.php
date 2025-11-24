<?php

declare(strict_types=1);

namespace Drupal\Tests\advancedqueue\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Exception\InvalidBackendException;
use Drupal\advancedqueue\Job;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that duplicate jobs cannot be queued.
 *
 * @group advancedqueue
 */
class DuplicatesUnsupportedBackendTest extends KernelTestBase {

  /**
   * A queue without unique job support.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * {@inheritdoc}
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
      'id' => 'duplicates_unsupported_queue',
      'label' => 'First queue',
      'backend' => 'base_only',
    ]);
    $this->queue->save();
  }

  /**
   * Tests that a job that avoids duplicates cannot be queued.
   */
  public function testAvoidDuplicates(): void {
    // A job that does not avoid duplicates can be queued.
    $job = Job::create('simple', ['test' => '1']);
    $this->queue->enqueueJob($job);

    $job = Job::create('avoid_duplicates', ['test' => '1']);
    $this->expectException(InvalidBackendException::class);
    $this->queue->enqueueJob($job);
  }

}
