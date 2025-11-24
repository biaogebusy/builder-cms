<?php

namespace Drupal\advancedqueue\QueueAction;

use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for queue actions.
 */
interface QueueActionInterface {

  /**
   * Gets the queue action ID.
   *
   * @return string
   *   The queue action ID.
   */
  public function id(): string;

  /**
   * Gets the queue action label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The queue action label.
   */
  public function label(): TranslatableMarkup;

  /**
   * Determines if the action can be executed for the queue and job.
   *
   * @param \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface $backend
   *   The queue backend to execute to action on.
   * @param string $job_state
   *   The state of the job.
   *
   * @return bool
   *   TRUE if the action is supported, FALSE if not.
   */
  public function canExecute(BackendInterface $backend, string $job_state): bool;

  /**
   * Executes the action for the supplied job ID on the queue backend.
   *
   * @param \Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface $backend
   *   The queue backend to execute to action on.
   * @param string $job_id
   *   The job ID to execute.
   *
   * @return bool
   *   TRUE if the action has been executed, FALSE if not.
   */
  public function execute(BackendInterface $backend, string $job_id): bool;

  /**
   * Gets the question to ask the user.
   *
   * @param int $count
   *   The number of jobs to process.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   The confirm form question.
   */
  public function confirmFormQuestion(int $count): PluralTranslatableMarkup;

  /**
   * Gets the message for the user to indicated what has been processed.
   *
   * @param int $count
   *   The number of jobs processed.
   *
   * @return \Drupal\Core\StringTranslation\PluralTranslatableMarkup
   *   The processed message.
   */
  public function processedMessage(int $count): PluralTranslatableMarkup;

}
