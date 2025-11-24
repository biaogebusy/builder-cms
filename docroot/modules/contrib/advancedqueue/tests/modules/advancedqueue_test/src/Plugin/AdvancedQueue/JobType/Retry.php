<?php

declare(strict_types=1);

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Attribute\AdvancedQueueJobType;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retry job type.
 */
#[AdvancedQueueJobType(
  id: "retry",
  label: new TranslatableMarkup("Retry"),
  max_retries: 1,
  retry_delay: 5,
)]
class Retry extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    return JobResult::failure();
  }

}
