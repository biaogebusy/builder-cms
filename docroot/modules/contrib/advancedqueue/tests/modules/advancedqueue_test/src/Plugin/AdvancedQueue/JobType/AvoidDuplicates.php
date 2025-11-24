<?php

declare(strict_types=1);

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Attribute\AdvancedQueueJobType;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Avoid duplicates job type.
 */
#[AdvancedQueueJobType(
  id: "avoid_duplicates",
  label: new TranslatableMarkup("Avoid duplicates"),
  allow_duplicates: FALSE,
)]
class AvoidDuplicates extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    return JobResult::success();
  }

}
