<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Job;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Provides the interface for queue backends.
 *
 * A backend is instantiated by the parent queue (config entity) and given
 * a queue ID. The handled queue is assumed to be "reliable" (in Drupal core
 * terms), meaning that the order of jobs is preserved, and every job is
 * guaranteed to be executed at least once.
 */
interface BackendInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Gets the backend label.
   *
   * @return string
   *   The backend label.
   */
  public function getLabel();

  /**
   * Creates the queue.
   *
   * Called upon creating the parent queue config entity, to allow backends
   * to initialize the queue remotely if needed.
   */
  public function createQueue();

  /**
   * Deletes the queue.
   *
   * Called upon deleting the parent queue config entity, to allow backends
   * to remove any leftover jobs and perform cleanup.
   */
  public function deleteQueue();

  /**
   * Cleans up the queue.
   *
   * Called by the queue runner before jobs are processed.
   */
  public function cleanupQueue();

  /**
   * Gets an estimated number of jobs in the queue.
   *
   * The accuracy of this number might vary.
   * On a busy system with a large number of consumers and jobs, the result
   * might only be valid for a fraction of a second and not provide an
   * accurate representation.
   *
   * @return array
   *   The estimated number of jobs, grouped per job status.
   *   Only the estimate for the 'queued' status is guaranteed to be present,
   *   other estimates (processing/success/failed) depend on backend
   *   capabilities and configuration.
   */
  public function countJobs();

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
   * Retries the given job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   * @param int $delay
   *   The time, in seconds, after which the retried job will become available
   *   to consumers. Defaults to 0, indicating no delay.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the given job is in an invalid state (not Job::STATE_FAILED).
   */
  public function retryJob(Job $job, $delay = 0);

  /**
   * Claims the next available job for processing.
   *
   * @return \Drupal\advancedqueue\Job|null
   *   The job, or NULL if none available.
   */
  public function claimJob();

  /**
   * Called when a job has been successfully processed.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   */
  public function onSuccess(Job $job);

  /**
   * Called when job processing has failed.
   *
   * Non-SQL backends that wish to preserve failed jobs can use this method
   * to populate a failure/dead-letter queue.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   */
  public function onFailure(Job $job);

}
