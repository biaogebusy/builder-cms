<?php

namespace Drupal\advancedqueue;

use Drupal\advancedqueue\Entity\QueueInterface;

/**
 * Provides the interface for queue processors.
 */
interface ProcessorInterface {

  /**
   * Processes the given queue.
   *
   * Jobs will be claimed and processed one by one until the configured
   * processing time ($queue->getProcessingTime()) passes.
   *
   * @param \Drupal\advancedqueue\Entity\QueueInterface $queue
   *   The queue.
   *
   * @return int
   *   The number of processed jobs.
   */
  public function processQueue(QueueInterface $queue);

  /**
   * Processes the given job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   * @param \Drupal\advancedqueue\Entity\QueueInterface $queue
   *   The parent queue.
   *
   * @return \Drupal\advancedqueue\JobResult
   *   The job result.
   */
  public function processJob(Job $job, QueueInterface $queue);

  /**
   * Stops the processing of the queue.
   */
  public function stop();

}
