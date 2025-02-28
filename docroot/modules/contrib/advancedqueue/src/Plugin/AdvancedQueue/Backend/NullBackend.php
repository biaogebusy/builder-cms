<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Job;

/**
 * Provides the null queue backend.
 *
 * This does not store jobs, and no jobs are ever available to be claimed.
 *
 * @AdvancedQueueBackend(
 *   id = "null_backend",
 *   label = @Translation("Null"),
 * )
 */
class NullBackend extends BackendBase implements SupportsDeletingJobsInterface, SupportsListingJobsInterface, SupportsReleasingJobsInterface {

  /**
   * {@inheritdoc}
   */
  public function createQueue() {}

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {}

  /**
   * {@inheritdoc}
   */
  public function cleanupQueue() {}

  /**
   * {@inheritdoc}
   */
  public function countJobs() {
    return [
      Job::STATE_QUEUED => 0,
      Job::STATE_PROCESSING => 0,
      Job::STATE_SUCCESS => 0,
      Job::STATE_FAILURE => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function enqueueJob(Job $job, $delay = 0) {}

  /**
   * {@inheritdoc}
   */
  public function enqueueJobs(array $jobs, $delay = 0) {}

  /**
   * {@inheritdoc}
   */
  public function retryJob(Job $job, $delay = 0) {}

  /**
   * {@inheritdoc}
   */
  public function claimJob() {
    // Jobs disappear into a void: there are no jobs to queue.
  }

  /**
   * {@inheritdoc}
   */
  public function onSuccess(Job $job) {}

  /**
   * {@inheritdoc}
   */
  public function onFailure(Job $job) {}

  /**
   * {@inheritdoc}
   */
  public function releaseJob($job_id) {}

  /**
   * {@inheritdoc}
   */
  public function deleteJob($job_id) {}

}
