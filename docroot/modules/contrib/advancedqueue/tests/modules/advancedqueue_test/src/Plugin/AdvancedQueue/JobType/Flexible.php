<?php

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;

/**
 * Flexible job type.
 *
 * @AdvancedQueueJobType(
 *   id = "flexible",
 *   label = @Translation("Flexible"),
 * )
 */
class Flexible extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    $payload = $job->getPayload();
    if (!empty($payload['expected_exception'])) {
      throw new \Exception($payload['expected_exception']);
    }

    return new JobResult(
      $payload['expected_state'],
      $payload['expected_message'],
      $payload['max_retries'] ?? NULL,
      $payload['retry_delay'] ?? NULL
    );
  }

}
