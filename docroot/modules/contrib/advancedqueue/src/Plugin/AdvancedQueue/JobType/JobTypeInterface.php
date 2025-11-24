<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines the interface for job types.
 *
 * Job types contain logic for processing a given job.
 * For example, sending an email or deleting an expired entity.
 */
interface JobTypeInterface extends PluginInspectionInterface {

  /**
   * Gets the job type label.
   *
   * @return string
   *   The job type label.
   */
  public function getLabel();

  /**
   * Gets the maximum number of retries.
   *
   * When job processing fails, the queue runner will retry the
   * job until the maximum number of retries is reached.
   * Defaults to 0, indicating that retries are disabled.
   *
   * @return int
   *   The job type label.
   */
  public function getMaxRetries();

  /**
   * Gets the retry delay.
   *
   * Represents the number of seconds that should pass before a retried
   * job becomes available again.
   *
   * @return int
   *   The retry delay.
   */
  public function getRetryDelay();

  /**
   * Creates a unique fingerprint for this job.
   *
   * This can be used for detecting duplicate jobs.
   *
   * @return string
   *   A hash that is unique for the job payload, job type, and the job's queue.
   */
  public function createJobFingerprint(Job $job): string;

  /**
   * Processes the given job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   *
   * @return \Drupal\advancedqueue\JobResult
   *   The job result.
   */
  public function process(Job $job);

  /**
   * Handles existing jobs detected as duplicates when enqueuing a new job.
   *
   * A function can be used to execute a range of different strategies with
   * regard to duplicate jobs:
   * - to pass the decision up to the job creator, throw DuplicateJobException;
   * - to enqueue the new job anyway, return the job;
   * - to overwrite the duplicate job, if the backend implements
   *   SupportsDeletingJobsInterface delete the duplicate job on the backend
   *   and return the new job;
   * - to merge the payloads, delete the duplicate job and return the new job
   *   with a modified payload;
   * - to discard the new job and leave the duplicate intact, return NULL.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The new job submitted for enqueueing.
   * @param array[\Drupal\advancedqueue\Job|string|int] $duplicates
   *   An array of jobs or job ids that are duplicates of the new job.
   *   Keyed by job id.
   * @param \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface $backend
   *   The backend being used for the jobs.
   *
   * @return \Drupal\advancedqueue\JobResult|null
   *   A new job to enqueue on the backend, or null if no new job should
   *   be enqueued.
   *
   * @throws \Drupal\advancedqueue\Exception\DuplicateJobException
   *   Thrown if the job should not be enqueued because it duplicates an
   *   existing job.
   */
  public function handleDuplicateJobs(Job $job, array $duplicates, BackendInterface $backend): ?Job;

}
