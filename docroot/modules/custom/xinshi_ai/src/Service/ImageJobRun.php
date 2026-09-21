<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/**
 * The lease one worker holds while it runs an image job (UB2.5).
 *
 * Handed to the task pipeline so the transport can record the dispatch, keep
 * the lease alive during the provider call and mark the moment the provider
 * answered. Every write presents the run token, so a run that lost its lease
 * to a recovery cannot alter the row any more.
 */
final class ImageJobRun {

  private int $lastHeartbeat = 0;

  public function __construct(
    private readonly ImageJobRuns $runs,
    public readonly string $jobUuid,
    public readonly string $token,
    private readonly ?array $previous,
  ) {}

  /**
   * The expired run this claim took over, or NULL for a first run or a retry.
   *
   * @return array<string,mixed>|null
   */
  public function previous(): ?array {
    return $this->previous;
  }

  /**
   * Called by the transport right before the image request leaves the process.
   */
  public function dispatched(): bool {
    return $this->runs->markDispatched($this);
  }

  /**
   * Called when the provider answered; from here on the supplier cost exists.
   */
  public function responded(): bool {
    return $this->runs->markResponded($this);
  }

  /**
   * Links the run to the metering attempt the pipeline opened.
   */
  public function usageAttempt(string $attemptId): void {
    $this->runs->setUsageAttempt($this, $attemptId);
  }

  /**
   * Lease renewal for the transport progress callback, throttled to one write per interval.
   *
   * @return bool
   *   FALSE when the lease was lost; the pipeline logs it and carries on, the
   *   status guards decide what the late result may still change.
   */
  public function heartbeat(): bool {
    $now = $this->runs->nowMs();
    if ($now - $this->lastHeartbeat < ImageJobRuns::HEARTBEAT_MS) {
      return TRUE;
    }
    $this->lastHeartbeat = $now;
    return $this->runs->renew($this);
  }

  public function release(): void {
    $this->runs->release($this);
  }

}
