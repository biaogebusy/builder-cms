<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

/**
 * Provides the interface for queue backends which support loading jobs.
 */
interface SupportsLoadingJobsInterface {

  /**
   * Loads the job with the given ID.
   *
   * @param string $job_id
   *   The job ID.
   *
   * @return \Drupal\advancedqueue\Job
   *   An object representing the loaded job.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the job cannot be loaded.
   */
  public function loadJob($job_id);

}
