<?php

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;

/**
 * Sleepy job type.
 *
 * @AdvancedQueueJobType(
 *   id = "sleepy",
 *   label = @Translation("Sleepy"),
 * )
 */
class Sleepy extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    sleep(1);
    return JobResult::success();
  }

}
