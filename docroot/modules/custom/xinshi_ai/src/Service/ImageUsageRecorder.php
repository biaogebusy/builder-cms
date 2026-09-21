<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\xinshi_ai_usage\Service\LocalUsageException;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Psr\Log\LoggerInterface;

/**
 * Opens usage attempts for image jobs before their provider call (UB2.4).
 *
 * Mirrors the Node `ModelUsage.create()` boundary: the intent is durable
 * before any provider I/O. In `observe` mode a write failure is logged and the
 * job runs unmetered; in `enforce` mode the job fails before the call.
 */
class ImageUsageRecorder {

  public const STAGE = 'image';
  public const LOGICAL_CALL = 'image';

  public function __construct(
    private readonly LocalUsageProducer $producer,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Records the intent of the call and returns its attempt handle.
   *
   * @param array $call
   *   operation_id (job uuid), kind (job kind), platform, model,
   *   actor_user_id, task_id (nullable).
   *
   * @return \Drupal\xinshi_ai\Service\ImageAttempt|null
   *   NULL when the fact could not be written in observe mode.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\LocalUsageException
   *   In enforce mode, when the intent cannot be persisted.
   */
  public function begin(array $call): ?ImageAttempt {
    $enforce = $this->producer->mode() === LocalUsageProducer::MODE_ENFORCE;
    $operationId = (string) $call['operation_id'];
    $customerKey = ($call['platform'] ?? '') === 'custom';
    try {
      // A queue item that runs again after a crash may follow a request whose
      // result never came back; that intent becomes unknown, not free.
      $this->producer->interruptOpenAttempts($operationId);
      $prepared = $this->producer->prepare([
        'operation_id' => $operationId,
        'logical_call_id' => self::LOGICAL_CALL,
        'feature' => (string) $call['kind'],
        'stage' => self::STAGE,
        'payer' => $customerKey ? 'customer_key' : 'platform',
        'billing_role' => 'primary',
        'provider_account_ref' => $customerKey ? 'customer_key' : (string) $call['platform'],
        'requested_model' => (string) $call['model'],
        'actor_user_id' => isset($call['actor_user_id']) ? (string) $call['actor_user_id'] : NULL,
        'task_id' => isset($call['task_id']) && $call['task_id'] !== '' ? (string) $call['task_id'] : NULL,
      ]);
    }
    catch (\Throwable $e) {
      if ($enforce) {
        throw $e instanceof LocalUsageException ? $e
          : new LocalUsageException('write_failed', 'Usage intent could not be written: ' . $e->getMessage(), $e);
      }
      $this->logger->error('Image job @job runs unmetered: usage intent could not be written (@message)', [
        '@job' => $operationId, '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
    return new ImageAttempt($this->producer, $this->logger, $prepared['site_id'], $operationId,
      $prepared['attempt_id'], $prepared['attempt_no']);
  }

  /**
   * Closes the intents a dead worker left open on a job (UB2.5).
   *
   * The lease row of the dead run says how far the request got: `not_sent`
   * when it never left the process (nothing was charged, the job may run
   * again), `sent` when the provider answered before the worker died, and
   * `unknown` in between (the supplier may have charged). The last two wait
   * for reconciliation. Bookkeeping only, so a failure is logged and never
   * stops the recovery.
   *
   * @return list<string>
   *   The attempt ids that were closed.
   */
  public function abandonOpenAttempts(string $operationId, string $dispatchState): array {
    try {
      return $this->producer->interruptOpenAttempts($operationId, $dispatchState);
    }
    catch (\Throwable $e) {
      $this->logger->error('Open usage intents of image job @job could not be closed as @state: @message', [
        '@job' => $operationId, '@state' => $dispatchState, '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

}
