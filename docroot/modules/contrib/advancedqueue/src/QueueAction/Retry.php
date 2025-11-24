<?php

namespace Drupal\advancedqueue\QueueAction;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsLoadingJobsInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retry job queue action.
 */
final class Retry implements QueueActionInterface {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'retry';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('Retry');
  }

  /**
   * {@inheritdoc}
   */
  public function canExecute(BackendInterface $backend, string $job_state): bool {
    return $job_state === Job::STATE_FAILURE && $backend instanceof SupportsLoadingJobsInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(BackendInterface $backend, string $job_id): bool {
    if ($backend instanceof SupportsLoadingJobsInterface) {
      $job = $backend->loadJob($job_id);
      if ($job && $job->getState() === Job::STATE_FAILURE) {
        $backend->retryJob($job);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmFormQuestion(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Are you sure you want to retry this job?', 'Are you sure you want to retry these jobs?');
  }

  /**
   * {@inheritdoc}
   */
  public function processedMessage(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Retried @count job', 'Retried @count jobs');
  }

}
