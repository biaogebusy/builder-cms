<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Job;

/**
 * Provides the interface for backends which support detecting duplicate jobs.
 */
interface SupportsDetectingDuplicateJobsInterface {

  /**
   * Get jobs queued in the backend that are duplicates of a possible job.
   *
   * Only jobs that are currently queued or processing are considered as
   * duplicates, not jobs that have succeeded or failed.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job to check duplicates for.
   *
   * @return array[\Drupal\advancedqueue\Job|int|string]
   *   An array of duplicate jobs if the backend can load them,
   *   or the duplicate job ids if the backend can detect the duplicate jobs
   *   but not load them. Keyed by job id. Does not include the passed job.
   *
   * @throws \InvalidArgumentException
   *   Throws an exception if the job does not have a fingerprint set.
   */
  public function getDuplicateJobs(Job $job): array;

}
