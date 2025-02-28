<?php

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;

/**
 * Retry job type.
 *
 * @AdvancedQueueJobType(
 *   id = "retry",
 *   label = @Translation("Retry"),
 *   max_retries = 1,
 *   retry_delay = 5,
 * )
 */
class Retry extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    return JobResult::failure();
  }

}
