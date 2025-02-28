<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\Backend;

/**
 * Provides the interface for queue backends which support deleting jobs.
 */
interface SupportsDeletingJobsInterface {

  /**
   * Deletes the job with the given ID.
   *
   * @param string $job_id
   *   The job ID.
   */
  public function deleteJob($job_id);

}
