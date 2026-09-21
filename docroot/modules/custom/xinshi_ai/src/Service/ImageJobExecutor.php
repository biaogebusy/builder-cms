<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiTaskManagerInterface;
use Drupal\xinshi_ai\Exception\TaskExecutionException;
use Psr\Log\LoggerInterface;

/**
 * Runs one queued image job under an execution lease (UB2.5).
 *
 * Decides from the stored job status and the lease table whether a delivered
 * queue item may call the model at all:
 * - a terminal job is dropped, so a redelivered item (cancelled while queued,
 *   crash between processItem and deleteItem, a second consumer) never sends
 *   the request again;
 * - a job whose lease a live worker holds is dropped, that worker owns it;
 * - a job whose lease expired belonged to a dead worker and is closed from
 *   the evidence that worker left instead of being run again blindly: a
 *   request that never left is safe to repeat, an in-flight request is
 *   unknown until an operator reconciles it, an answered request is final
 *   with whatever was saved.
 */
final class ImageJobExecutor {

  public const QUEUE = 'xinshi_ai_image_job';
  /** Retries after the first run; retryable errors are re-queued immediately. */
  public const MAX_RETRIES = 2;
  public const RESULT_UNKNOWN = 'result_unknown';
  public const PROCESS_INTERRUPTED = 'process_interrupted';
  private const TERMINAL = ['succeeded', 'failed', 'cancelled'];

  public function __construct(
    private readonly AiTaskManagerInterface $taskManager,
    private readonly JobLifecycleServiceInterface $lifecycle,
    private readonly ProviderErrorMapper $errorMapper,
    private readonly QueueFactory $queueFactory,
    private readonly EventStreamServiceInterface $eventStream,
    private readonly ImageJobRuns $runs,
    private readonly ImageUsageRecorder $usageRecorder,
    private readonly LoggerInterface $logger,
    private readonly ImageJobCredentialVault $credentials,
  ) {}

  /**
   * Processes one queue item.
   *
   * @param array $data
   *   `jobUuid` and the retry counter `attempt` (0 for the first delivery).
   */
  public function run(array $data): void {
    $uuid = (string) ($data['jobUuid'] ?? '');
    $job = $uuid === '' ? NULL : $this->lifecycle->loadJobByUuid($uuid);
    if ($job === NULL) {
      return;
    }
    $queueAttempt = (int) ($data['attempt'] ?? 0);
    if ($this->isTerminal($job)) {
      $this->logger->notice('image_job @u is already @s; the redelivered queue item is dropped without calling the model', [
        '@u' => $uuid,
        '@s' => $job->get('field_status')->value,
      ]);
      $this->credentials->delete($uuid);
      return;
    }
    $run = $this->runs->claim($uuid, $queueAttempt);
    if ($run === NULL) {
      $this->logger->notice('image_job @u is being run by another worker; the duplicate queue item is dropped', [
        '@u' => $uuid,
      ]);
      return;
    }
    try {
      if ($run->previous() === NULL || $this->recover($job, $run)) {
        $this->execute($job, $run, $queueAttempt);
      }
    }
    finally {
      $run->release();
    }
    // A job that ended (any outcome) no longer needs its customer key; one
    // that was re-queued for a retry keeps it until that retry ends.
    if ($this->isTerminal($job)) {
      $this->credentials->delete($uuid);
    }
  }

  /**
   * Closes jobs whose worker died, from cron.
   *
   * A queue item redelivered while the dead worker's lease is still live is
   * dropped, so the queue alone would leave such a job running forever.
   *
   * @return int
   *   Number of expired runs that were taken over.
   */
  public function recoverExpired(): int {
    $recovered = 0;
    foreach ($this->runs->expired() as $uuid) {
      $run = $this->runs->claim($uuid, 0);
      if ($run === NULL) {
        continue;
      }
      $recovered++;
      try {
        $job = $this->lifecycle->loadJobByUuid($uuid);
        if ($job === NULL) {
          $this->credentials->delete($uuid);
          continue;
        }
        if ($this->isTerminal($job) || $run->previous() === NULL) {
          // Finished by its worker right before it died.
          $this->credentials->delete($uuid);
          continue;
        }
        if ($this->recover($job, $run)) {
          // The request never left: queue the job again rather than calling
          // the model inside cron's time budget.
          $this->queueFactory->get(self::QUEUE)->createItem([
            'jobUuid' => $uuid,
            'attempt' => (int) $run->previous()['queue_attempt'],
          ]);
        }
        else {
          $this->credentials->delete($uuid);
        }
      }
      finally {
        $run->release();
      }
    }
    return $recovered;
  }

  /**
   * Resolves the run of a dead worker from the evidence it left.
   *
   * @return bool
   *   TRUE when the model was never called, so the job may run now.
   */
  private function recover(NodeInterface $job, ImageJobRun $run): bool {
    $previous = $run->previous();
    $uuid = $job->uuid();
    if ($previous['responded_at'] !== NULL) {
      // The provider answered and the worker died while saving images. The
      // usage was normally observed from the response; an intent still open
      // here is a sent request whose usage was lost, and what was saved is
      // delivered. The rest is not generated again on the user's bill.
      $this->usageRecorder->abandonOpenAttempts($uuid, 'sent');
      $saved = (int) $job->get('field_n_succeeded')->value;
      $reason = sprintf('Worker interrupted after the provider responded; %d of %d images were saved.',
        $saved, (int) $job->get('field_n_requested')->value);
      $this->logger->error('image_job @u: @reason', ['@u' => $uuid, '@reason' => $reason]);
      if ($this->lifecycle->finalizeInterrupted($job, $reason, self::PROCESS_INTERRUPTED)) {
        $this->publishTerminal($job);
      }
      return FALSE;
    }
    if ($previous['dispatched_at'] !== NULL) {
      // In flight when the worker died: the supplier may have charged. The
      // attempt stays unknown until an operator reconciles it; the job is not
      // run again on the user's account.
      $this->usageRecorder->abandonOpenAttempts($uuid, 'unknown');
      $reason = 'Worker interrupted while the provider request was in flight; the result is unknown and needs reconciliation.';
      $this->logger->error('image_job @u: @reason', ['@u' => $uuid, '@reason' => $reason]);
      if ($this->lifecycle->markFailed($job, $reason, self::RESULT_UNKNOWN)) {
        $this->eventStream->publish($uuid, 'failed', [
          'statusReason' => $reason,
          'errorCode' => self::RESULT_UNKNOWN,
        ]);
      }
      return FALSE;
    }
    // The request never left the process: nothing was charged, run it again.
    $this->usageRecorder->abandonOpenAttempts($uuid, 'not_sent');
    $this->logger->warning('image_job @u: worker interrupted before the provider request was sent; running the job again', [
      '@u' => $uuid,
    ]);
    return TRUE;
  }

  private function execute(NodeInterface $job, ImageJobRun $run, int $queueAttempt): void {
    $uuid = $job->uuid();
    try {
      $task = $this->taskManager->getByKind((string) $job->get('field_job_kind')->value);
      $task->execute($job, $run);
    }
    catch (TaskExecutionException $e) {
      $this->handleFailure($job, $uuid, $e->errorCode, $e->getMessage(), $queueAttempt);
    }
    catch (\Throwable $e) {
      // An error outside the task pipeline is not retried.
      $this->handleFailure($job, $uuid, 'internal', $e->getMessage(), self::MAX_RETRIES);
    }
  }

  /**
   * Re-queues a retryable failure or records the terminal failure.
   *
   * The message is stored on the job and logged, so the customer's key is
   * removed from it first: a provider error may echo the request it rejected.
   */
  private function handleFailure(NodeInterface $job, string $uuid, string $code, string $message, int $attempt): void {
    $message = $this->credentials->redact($uuid, $message);
    if ($this->isTerminal($job)) {
      // Cancelled (or otherwise closed) while the call was running: that
      // decision stands, the failure is neither retried nor recorded over it.
      $this->logger->notice('image_job @u failed (@c) after it was already @s; nothing to retry', [
        '@u' => $uuid,
        '@c' => $code,
        '@s' => $job->get('field_status')->value,
      ]);
      return;
    }
    if ($this->errorMapper->isRetryable($code) && $attempt < self::MAX_RETRIES) {
      $this->logger->warning('image_job 重试 job=@u code=@c attempt=@a: @m', [
        '@u' => $uuid,
        '@c' => $code,
        '@a' => $attempt + 1,
        '@m' => $message,
      ]);
      $this->queueFactory->get(self::QUEUE)->createItem([
        'jobUuid' => $uuid,
        'attempt' => $attempt + 1,
      ]);
      return;
    }

    $this->logger->error('image_job 失败(终态)job=@u code=@c attempt=@a: @m', [
      '@u' => $uuid,
      '@c' => $code,
      '@a' => $attempt,
      '@m' => $message,
    ]);
    if ($this->lifecycle->markFailed($job, $message, $code)) {
      $this->eventStream->publish($uuid, 'failed', [
        'statusReason' => $message,
        'errorCode' => $code,
      ]);
    }
  }

  private function isTerminal(NodeInterface $job): bool {
    $status = $this->lifecycle->refreshStatus($job);
    return $status === NULL || in_array($status, self::TERMINAL, TRUE);
  }

  private function publishTerminal(NodeInterface $job): void {
    $uuid = $job->uuid();
    if ($job->get('field_status')->value === 'succeeded') {
      $this->eventStream->publish($uuid, 'completed', [
        'nSucceeded' => (int) $job->get('field_n_succeeded')->value,
        'completedAt' => (int) $job->get('field_completed_at')->value,
      ]);
      return;
    }
    $this->eventStream->publish($uuid, 'failed', [
      'statusReason' => (string) $job->get('field_status_reason')->value,
      'errorCode' => (string) $job->get('field_error_code')->value,
    ]);
  }

}
