<?php

declare(strict_types=1);

namespace Drupal\advancedqueue_test\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Attribute\AdvancedQueueBackend;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A test backend that only extends BackendBase.
 *
 * By design, it does not implement any additional interfaces.
 */
#[AdvancedQueueBackend(
  id: "base_only",
  label: new TranslatableMarkup("Base only"),
)]
class BaseOnly extends BackendBase {

  /**
   * {@inheritdoc}
   */
  public function enqueueJob(Job $job, $delay = 0): void {
    $this->enqueueJobs([$job], $delay);
  }

  /**
   * {@inheritdoc}
   */
  public function enqueueJobs(array $jobs, $delay = 0): void {}

  /**
   * {@inheritdoc}
   */
  public function createQueue(): void {}

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(): void {}

  /**
   * {@inheritdoc}
   */
  public function countJobs(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function retryJob(Job $job, $delay = 0): void {}

  /**
   * {@inheritdoc}
   */
  public function claimJob(): ?Job {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function onSuccess(Job $job): void {}

  /**
   * {@inheritdoc}
   */
  public function onFailure(Job $job): void {}

  /**
   * {@inheritdoc}
   */
  public function releaseJob($job_id): void {}

  /**
   * {@inheritdoc}
   */
  public function deleteJob($job_id): void {}

}
