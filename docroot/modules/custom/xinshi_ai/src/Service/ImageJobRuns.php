<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Execution leases of image jobs (UB2.5).
 *
 * Drupal's queue lease covers the queue item, not the job: cron and
 * `drush queue:run` lease an item for the worker's cron time (60 seconds)
 * while one image request may take three minutes, and a job can be enqueued
 * more than once (retry, crash before the item was deleted, several
 * consumers). This table holds one row per job so that at most one worker
 * runs a job at a time, and it keeps the two facts a recovery needs when that
 * worker dies: whether the image request left the process and whether the
 * provider answered. Rows are never deleted; a finished row is claimable again
 * for a retry, a running row past its lease belongs to a dead worker.
 */
class ImageJobRuns {

  public const TABLE = 'xinshi_ai_image_job_run';
  public const STATE_RUNNING = 'running';
  public const STATE_FINISHED = 'finished';
  /** Lease length; the transport heartbeat renews it during the provider call. */
  public const LEASE_MS = 120_000;
  /** Minimum gap between two renewals triggered by the transport heartbeat. */
  public const HEARTBEAT_MS = 30_000;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Claims a job for the calling worker.
   *
   * @return \Drupal\xinshi_ai\Service\ImageJobRun|null
   *   NULL when another worker holds a live lease. A run whose previous() is
   *   not NULL took over the expired lease of a dead worker and must resolve
   *   that run's evidence before deciding whether to call the model.
   */
  public function claim(string $jobUuid, int $queueAttempt): ?ImageJobRun {
    $now = $this->nowMs();
    $token = $this->uuid->generate();
    $fields = [
      'run_token' => $token,
      'queue_attempt' => $queueAttempt,
      'worker' => $this->worker(),
      'usage_attempt_id' => NULL,
      'state' => self::STATE_RUNNING,
      'claimed_at' => $now,
      'lease_until' => $now + self::LEASE_MS,
      'dispatched_at' => NULL,
      'responded_at' => NULL,
      'finished_at' => NULL,
    ];
    try {
      $this->database->insert(self::TABLE)->fields(['job_uuid' => $jobUuid] + $fields)->execute();
      return new ImageJobRun($this, $jobUuid, $token, NULL);
    }
    catch (IntegrityConstraintViolationException) {
      // Another run of this job exists or existed; decided below.
    }
    $current = $this->row($jobUuid);
    if ($current === NULL) {
      return NULL;
    }
    $running = $current['state'] === self::STATE_RUNNING;
    if ($running && (int) $current['lease_until'] > $now) {
      return NULL;
    }
    // A finished row (retry after a handled failure) or an expired lease: take
    // it over, unless another worker did so in between.
    $updated = $this->database->update(self::TABLE)
      ->fields($fields)
      ->condition('job_uuid', $jobUuid)
      ->condition('run_token', (string) $current['run_token'])
      ->condition('state', (string) $current['state'])
      ->condition('lease_until', (int) $current['lease_until'])
      ->execute();
    if ($updated !== 1) {
      return NULL;
    }
    return new ImageJobRun($this, $jobUuid, $token, $running ? $current : NULL);
  }

  /**
   * Extends the lease of a live run; FALSE when the run no longer holds it.
   */
  public function renew(ImageJobRun $run): bool {
    return $this->touch($run, ['lease_until' => $this->nowMs() + self::LEASE_MS]);
  }

  /**
   * Records that the image request is leaving the process.
   */
  public function markDispatched(ImageJobRun $run): bool {
    $now = $this->nowMs();
    return $this->touch($run, ['dispatched_at' => $now, 'lease_until' => $now + self::LEASE_MS]);
  }

  /**
   * Records that the provider answered; the supplier cost exists from here on.
   */
  public function markResponded(ImageJobRun $run): bool {
    $now = $this->nowMs();
    return $this->touch($run, ['responded_at' => $now, 'lease_until' => $now + self::LEASE_MS]);
  }

  /**
   * Links the run to the metering attempt it opened.
   */
  public function setUsageAttempt(ImageJobRun $run, string $attemptId): bool {
    return $this->touch($run, ['usage_attempt_id' => $attemptId]);
  }

  /**
   * Ends the run; the row stays as the record of the last execution.
   */
  public function release(ImageJobRun $run): void {
    $this->touch($run, ['state' => self::STATE_FINISHED, 'finished_at' => $this->nowMs()]);
  }

  /**
   * Jobs whose running lease expired: their worker died mid-run.
   *
   * @return list<string>
   *   Job UUIDs, oldest expiry first.
   */
  public function expired(int $limit = 50): array {
    return $this->database->select(self::TABLE, 'r')
      ->fields('r', ['job_uuid'])
      ->condition('r.state', self::STATE_RUNNING)
      ->condition('r.lease_until', $this->nowMs(), '<=')
      ->orderBy('r.lease_until')
      ->orderBy('r.job_uuid')
      ->range(0, max(1, $limit))
      ->execute()
      ->fetchCol();
  }

  /**
   * The lease row of a job, or NULL before its first run.
   *
   * @return array<string,mixed>|null
   */
  public function row(string $jobUuid): ?array {
    $row = $this->database->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('r.job_uuid', $jobUuid)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

  private function touch(ImageJobRun $run, array $fields): bool {
    return $this->database->update(self::TABLE)
      ->fields($fields)
      ->condition('job_uuid', $run->jobUuid)
      ->condition('run_token', $run->token)
      ->condition('state', self::STATE_RUNNING)
      ->execute() === 1;
  }

  private function worker(): string {
    return mb_substr((string) gethostname() . ':' . getmypid(), 0, 191);
  }

}
