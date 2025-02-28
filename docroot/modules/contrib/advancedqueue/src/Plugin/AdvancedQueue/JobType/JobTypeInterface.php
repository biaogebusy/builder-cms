<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
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
   * Processes the given job.
   *
   * @param \Drupal\advancedqueue\Job $job
   *   The job.
   *
   * @return \Drupal\advancedqueue\JobResult
   *   The job result.
   */
  public function process(Job $job);

}
