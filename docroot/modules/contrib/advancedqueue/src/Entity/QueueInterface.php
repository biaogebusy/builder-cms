<?php

namespace Drupal\advancedqueue\Entity;

use Drupal\advancedqueue\Job;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Defines the interface for queues.
 *
 * The enqueueJob() and enqueueJobs() methods are copied from BackendInterface
 * for DX reasons. Most modules will only interact with those methods, so
 * this saves them a getBackend() call.
 */
interface QueueInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Available queue processors.
   */
  const PROCESSOR_CRON = 'cron';
  const PROCESSOR_DAEMON = 'daemon';

  /**
   * Type of expiration queues.
   */
  const QUEUE_THRESHOLD_ITEMS = 1;
  const QUEUE_THRESHOLD_DAYS = 2;

  /**
   * Queue threshold by items count.
   */
  const QUEUE_THRESHOLD_ITEMS_LIMITS = [
    100,
    1000,
    10000,
    100000,
    1000000,
  ];

  /**
   * Queue limits by days.
   */
  const QUEUE_THRESHOLD_DAYS_LIMITS = [
    7,
    30,
    60,
    180,
    365,
  ];

  /**
   * Enqueues the given job.
   *
   * The job will be modified with the assigned queue ID, job ID, and
   * relevant timestamps.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   * @param int $delay
   *   The time, in seconds, after which the job will become available to
   *   consumers. Defaults to 0, indicating no delay.
   */
  public function enqueueJob(Job $job, $delay = 0);

  /**
   * Enqueues the given jobs.
   *
   * Each job will be modified with the assigned queue ID, job ID, and
   * relevant timestamps.
   *
   * @param \Drupal\advancedqueue\Job[] $jobs
   *   The jobs.
   * @param int $delay
   *   The time, in seconds, after which the jobs will become available to
   *   consumers. Defaults to 0, indicating no delay.
   */
  public function enqueueJobs(array $jobs, $delay = 0);

  /**
   * Gets the backend plugin.
   *
   * @return \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface
   *   The backend plugin.
   */
  public function getBackend();

  /**
   * Gets the backend plugin ID.
   *
   * @return string
   *   The backend plugin ID.
   */
  public function getBackendId();

  /**
   * Sets the backend plugin ID.
   *
   * @param string $backend_id
   *   The backend plugin ID.
   *
   * @return $this
   */
  public function setBackendId($backend_id);

  /**
   * Gets the backend plugin configuration.
   *
   * @return string
   *   The backend plugin configuration.
   */
  public function getBackendConfiguration();

  /**
   * Sets the backend plugin configuration.
   *
   * @param array $configuration
   *   The backend plugin configuration.
   *
   * @return $this
   */
  public function setBackendConfiguration(array $configuration);

  /**
   * Gets the queue processor.
   *
   * @return string
   *   The queue processor, one of the PROCESSOR_ constants.
   */
  public function getProcessor();

  /**
   * Sets the queue processor.
   *
   * @param string $processor
   *   The queue processor, one of the PROCESSOR_ constants.
   *
   * @return $this
   */
  public function setProcessor($processor);

  /**
   * Gets the queue processing time.
   *
   * Indicates how long the processor should process the queue.
   *
   * @return int
   *   The queue processing time, in seconds. 0 for unlimited.
   */
  public function getProcessingTime();

  /**
   * Sets the queue processing time.
   *
   * @param int $processing_time
   *   The queue processing time, in seconds. 0 for unlimited.
   *
   * @return $this
   */
  public function setProcessingTime($processing_time);

  /**
   * Gets the number of queue items to keep.
   *
   * @return array
   *   The array of type, limit and state of the cleanup threshold where:
   *   - type is either 0 (keep all), QUEUE_THRESHOLD_ITEMS or
   *     QUEUE_THRESHOLD_DAYS;
   *   - limit is the number of items or days to keep;
   *   - state is either 'all' or Job::STATE_SUCCESS to remove all items or
   *     only the ones that have finished successfully.
   */
  public function getThreshold();

  /**
   * Set the number of queue items to keep.
   *
   * @param array $threshold
   *   An array containing the type, limit and state of the cleanup threshold.
   *
   * @return $this
   */
  public function setThreshold(array $threshold);

  /**
   * Gets whether the queue is locked.
   *
   * Locked queues cannot be deleted.
   *
   * @return bool
   *   TRUE if the queue is locked, FALSE otherwise.
   */
  public function isLocked();

}
