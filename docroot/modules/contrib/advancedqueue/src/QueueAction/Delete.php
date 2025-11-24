<?php

namespace Drupal\advancedqueue\QueueAction;

use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\SupportsDeletingJobsInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Delete job queue action.
 */
final class Delete implements QueueActionInterface {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'delete';
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    return new TranslatableMarkup('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function canExecute(BackendInterface $backend, string $job_state): bool {
    return $backend instanceof SupportsDeletingJobsInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(BackendInterface $backend, string $job_id): bool {
    if ($backend instanceof SupportsDeletingJobsInterface) {
      $backend->deleteJob($job_id);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmFormQuestion(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Are you sure you want to delete this job?', 'Are you sure you want to delete these jobs?');
  }

  /**
   * {@inheritdoc}
   */
  public function processedMessage(int $count): PluralTranslatableMarkup {
    return new PluralTranslatableMarkup($count, 'Deleted @count job', 'Deleted @count jobs');
  }

}
