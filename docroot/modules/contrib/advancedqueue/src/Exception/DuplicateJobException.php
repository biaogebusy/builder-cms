<?php

namespace Drupal\advancedqueue\Exception;

use Drupal\advancedqueue\Job;

/**
 * Exception thrown when a job is detected as a duplicate.
 *
 * This is thrown when a new job being considered for queueing is recognized as
 * a duplicate of one or more jobs that are already queued.
 */
class DuplicateJobException extends \Exception {

  /**
   * The job submitted for enqueueing.
   *
   * @var \Drupal\advancedqueue\Job
   */
  protected Job $job;

  /**
   * An array of duplicate jobs or duplicate job ids. Keyed by job id.
   *
   * @var array[\Drupal\advancedqueue\Job|int|string]
   */
  protected array $duplicates;

  /**
   * Constructs a DuplicateJobException.
   *
   * @param string $message
   *   A message describing the duplicate situation.
   * @param \Drupal\advancedqueue\Job $job
   *   The job that was submitted for enqueueing which had existing duplicates.
   * @param array[\Drupal\advancedqueue\Job|int|string] $duplicates
   *   An array of duplicate jobs or duplicate job ids. Keyed by job id.
   */
  public function __construct($message, Job $job, array $duplicates) {
    parent::__construct($message);
    $this->job = $job;
    $this->duplicates = $duplicates;
  }

  /**
   * Get the job submitted for enqueueing.
   *
   * @return \Drupal\advancedqueue\Job
   *   The job.
   */
  public function getJob() {
    return $this->job;
  }

  /**
   * Get the existing jobs detected as duplicates.
   *
   * @return array[\Drupal\advancedqueue\Job|int|string]
   *   An array of duplicate jobs or duplicate job ids. Keyed by job id.
   */
  public function getDuplicates() {
    return $this->duplicates;
  }

}
