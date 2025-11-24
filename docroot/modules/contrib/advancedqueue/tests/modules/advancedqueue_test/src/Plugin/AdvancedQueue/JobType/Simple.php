<?php

declare(strict_types=1);

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Attribute\AdvancedQueueJobType;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Simple job type.
 */
#[AdvancedQueueJobType(
  id: "simple",
  label: new TranslatableMarkup("Simple"),
)]
class Simple extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    return JobResult::success();
  }

}
