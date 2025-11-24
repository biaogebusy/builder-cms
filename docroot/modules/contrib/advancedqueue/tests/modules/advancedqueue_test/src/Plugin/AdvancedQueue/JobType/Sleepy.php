<?php

declare(strict_types=1);

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Attribute\AdvancedQueueJobType;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sleepy job type.
 */
#[AdvancedQueueJobType(
  id: "sleepy",
  label: new TranslatableMarkup("Sleepy"),
)]
class Sleepy extends JobTypeBase {

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    sleep(1);
    return JobResult::success();
  }

}
