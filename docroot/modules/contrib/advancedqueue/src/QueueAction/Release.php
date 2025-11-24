<?php

namespace Drupal\advancedqueue\QueueAction;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsReleasingJobsInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Release job queue action.
 */
final class Release implements QueueActionInterface {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'release';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('Release');
  }

  /**
   * {@inheritdoc}
   */
  public function canExecute(BackendInterface $backend, string $job_state): bool {
    return $job_state === Job::STATE_PROCESSING && $backend instanceof SupportsReleasingJobsInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(BackendInterface $backend, string $job_id): bool {
    if ($backend instanceof SupportsReleasingJobsInterface) {
      $backend->releaseJob($job_id);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmFormQuestion(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Are you sure you want to release this job?', 'Are you sure you want to release these jobs?');
  }

  /**
   * {@inheritdoc}
   */
  public function processedMessage(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Released @count job', 'Released @count jobs');
  }

}
