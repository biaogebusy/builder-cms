<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

/**
 * Provides the interface for queue backends which support releasing jobs.
 */
interface SupportsReleasingJobsInterface {

  /**
   * Releases the job with the given ID.
   *
   * @param string $job_id
   *   The job ID.
   */
  public function releaseJob($job_id);

}
