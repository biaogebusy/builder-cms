<?php

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;

/**
 * Simple job type.
 *
 * @AdvancedQueueJobType(
 *   id = "simple",
 *   label = @Translation("Simple"),
 * )
 */
class Simple extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    return JobResult::success();
  }

}
