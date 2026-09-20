<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\xinshi_ai_usage\Contract\UsageContractException;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use Psr\Log\LoggerInterface;

/**
 * Rebuilds the attempt projection and hourly rollup from immutable usage events (UB1.6).
 *
 * The consumer claims events whose processed_at is NULL in id order. Each event
 * is applied in one database transaction that covers the projection row, the
 * immutable cost entry, the affected rollup cells, the watermark and the
 * processed_at acknowledgement. A crash before commit leaves the event
 * unprocessed; a crash after commit leaves nothing to redo, so replaying never
 * double counts.
 *
 * Ordering rules follow the billing contract: observation revisions are
 * monotonic per attempt, the projection keeps the highest applied revision, a
 * lower revision arriving later only leaves its audit trail (event row and
 * cost entry) and never overwrites the projection. Rollup cells are recomputed
 * from the projection rows they cover instead of accumulating deltas, so
 * re-application of any event is idempotent by construction.
 */
final class UsageProjectionService {

  public const ATTEMPT_TABLE = 'ai_provider_attempt';
  public const ROLLUP_TABLE = 'ai_usage_rollup_hour';
  public const WATERMARK_TABLE = 'ai_usage_watermark';
  public const PROJECTION = 'attempt';
  public const PROJECTION_VERSION = 1;
  public const LOCK_NAME = 'xinshi_ai_usage.projection';
  public const DEFAULT_LIMIT = 200;
  /** Failures before an event is quarantined and stops being claimed. */
  public const MAX_FAILURES = 5;
  public const HOUR_MS = 3_600_000;
  /** Sentinel for rollup dimensions that are absent on the attempt. */
  public const NONE = '-';

  private const OPEN_STATES = ['prepared', 'in_flight'];
  private const FINISHED_STATES = ['succeeded', 'failed', 'unknown'];
  private const CELL_DIMENSIONS = ['bucket_start', 'feature', 'stage', 'payer', 'billing_role',
    'provider_account_ref', 'model_id', 'actor_user_id', 'currency'];

  public function __construct(
    private readonly Connection $database,
    private readonly CostRatingService $costRating,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Applies pending events, oldest first, under a single-consumer lock.
   *
   * @param string|null $siteId
   *   Restrict to one site, or NULL for every site.
   * @param int $limit
   *   Maximum number of events to claim in this run.
   *
   * @return array{locked:bool,claimed:int,applied:int,audited:int,skipped:int,failed:int,quarantined:int,remaining:int}
   *   `applied` changed the projection, `audited` were duplicates or stale
   *   revisions, `skipped` could not be parsed and were acknowledged,
   *   `failed` were left unprocessed for the next run, `quarantined` is the
   *   number of events set aside after MAX_FAILURES and needing an operator.
   */
  public function process(?string $siteId = NULL, int $limit = self::DEFAULT_LIMIT): array {
    $stats = ['locked' => FALSE, 'claimed' => 0, 'applied' => 0, 'audited' => 0,
      'skipped' => 0, 'failed' => 0, 'quarantined' => 0, 'remaining' => 0];
    if (!$this->lock->acquire(self::LOCK_NAME, 300.0)) {
      $stats['locked'] = TRUE;
      return $stats;
    }
    try {
      foreach ($this->claim($siteId, max(1, $limit)) as $row) {
        $stats['claimed']++;
        try {
          $stats[$this->applyEvent($row)]++;
        }
        catch (\Throwable $e) {
          $stats['failed']++;
          $this->recordFailure($row, $e);
        }
      }
      $stats['remaining'] = $this->pendingCount($siteId);
      $stats['quarantined'] = $this->quarantinedCount($siteId);
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
    return $stats;
  }

  /**
   * Drops the projection, rollup and watermark of a site and replays its events.
   *
   * Cost entries are immutable facts and stay; re-rating them is an idempotent
   * no-op, so the rebuilt projection carries the same cost snapshot. Quarantined
   * events get a fresh chance, since the replay may follow a code fix.
   *
   * @return array{locked:bool,claimed:int,applied:int,audited:int,skipped:int,failed:int,quarantined:int,remaining:int}
   */
  public function rebuild(string $siteId): array {
    if (!$this->lock->acquire(self::LOCK_NAME, 300.0)) {
      return ['locked' => TRUE, 'claimed' => 0, 'applied' => 0, 'audited' => 0, 'skipped' => 0,
        'failed' => 0, 'quarantined' => $this->quarantinedCount($siteId),
        'remaining' => $this->pendingCount($siteId)];
    }
    try {
      $tx = $this->database->startTransaction();
      try {
        $this->database->delete(self::ATTEMPT_TABLE)->condition('site_id', $siteId)->execute();
        $this->database->delete(self::ROLLUP_TABLE)->condition('site_id', $siteId)->execute();
        $this->database->delete(self::WATERMARK_TABLE)->condition('site_id', $siteId)->execute();
        $this->database->update(UsageIngestService::TABLE)
          ->fields(['processed_at' => NULL, 'failure_count' => 0, 'last_error' => NULL,
            'quarantined_at' => NULL])
          ->condition('site_id', $siteId)
          ->execute();
      }
      catch (\Throwable $e) {
        $tx->rollBack();
        throw $e;
      }
      unset($tx);
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
    $total = ['locked' => FALSE, 'claimed' => 0, 'applied' => 0, 'audited' => 0,
      'skipped' => 0, 'failed' => 0, 'quarantined' => 0, 'remaining' => 0];
    do {
      $run = $this->process($siteId, self::DEFAULT_LIMIT);
      foreach (['claimed', 'applied', 'audited', 'skipped', 'failed'] as $key) {
        $total[$key] += $run[$key];
      }
      $total['locked'] = $run['locked'];
      $total['quarantined'] = $run['quarantined'];
      $total['remaining'] = $run['remaining'];
      // Stop when nothing moved: either a lock, or only failing events remain.
    } while (!$run['locked'] && $run['remaining'] > 0 && $run['claimed'] > $run['failed']);
    return $total;
  }

  /**
   * Returns the watermark row of a site, or NULL before the first event.
   *
   * @return array{projection_version:int,last_event_id:int,processed_count:int,data_as_of:int,updated_at:int}|null
   */
  public function watermark(string $siteId): ?array {
    $row = $this->database->select(self::WATERMARK_TABLE, 'w')
      ->fields('w', ['projection_version', 'last_event_id', 'processed_count', 'data_as_of', 'updated_at'])
      ->condition('site_id', $siteId)
      ->condition('projection', self::PROJECTION)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : array_map('intval', $row);
  }

  /**
   * Number of events not yet acknowledged and not quarantined.
   */
  public function pendingCount(?string $siteId = NULL): int {
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNull('e.processed_at')
      ->isNull('e.quarantined_at');
    if ($siteId !== NULL) {
      $query->condition('e.site_id', $siteId);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Number of events set aside after repeated failures; they need an operator.
   */
  public function quarantinedCount(?string $siteId = NULL): int {
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNotNull('e.quarantined_at');
    if ($siteId !== NULL) {
      $query->condition('e.site_id', $siteId);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Returns quarantined events to the queue, e.g. after a code or data fix.
   *
   * @return int
   *   Number of events released.
   */
  public function releaseQuarantined(?string $siteId = NULL): int {
    $update = $this->database->update(UsageIngestService::TABLE)
      ->fields(['failure_count' => 0, 'last_error' => NULL, 'quarantined_at' => NULL])
      ->isNotNull('quarantined_at');
    if ($siteId !== NULL) {
      $update->condition('site_id', $siteId);
    }
    return (int) $update->execute();
  }

  /**
   * @return iterable<array<string,mixed>>
   */
  private function claim(?string $siteId, int $limit): iterable {
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['id', 'site_id', 'producer_id', 'event_id', 'event_type', 'attempt_id',
        'observation_revision', 'occurred_at', 'failure_count', 'payload_json'])
      ->isNull('e.processed_at')
      ->isNull('e.quarantined_at')
      ->orderBy('e.id')
      ->range(0, $limit);
    if ($siteId !== NULL) {
      $query->condition('e.site_id', $siteId);
    }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Counts a failed application outside the rolled-back transaction.
   *
   * The event stays pending until MAX_FAILURES, then it is quarantined so a
   * permanently failing event cannot occupy the batch forever. Quarantine is
   * bookkeeping only; the fact itself is untouched.
   */
  private function recordFailure(array $row, \Throwable $e): void {
    $failures = (int) $row['failure_count'] + 1;
    $quarantine = $failures >= self::MAX_FAILURES;
    $this->database->update(UsageIngestService::TABLE)
      ->fields([
        'failure_count' => $failures,
        'last_error' => mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 255),
        'quarantined_at' => $quarantine ? $this->nowMs() : NULL,
      ])
      ->condition('id', (int) $row['id'])
      ->execute();
    $this->logger->error('Usage event @id (@site) could not be applied (failure @count of @max@quarantined): @message', [
      '@id' => $row['event_id'], '@site' => $row['site_id'], '@count' => $failures,
      '@max' => self::MAX_FAILURES, '@quarantined' => $quarantine ? ', quarantined' : '',
      '@message' => $e->getMessage(),
    ]);
  }

  /**
   * Applies one event in its own transaction and acknowledges it.
   *
   * @return string
   *   One of applied, audited, skipped.
   */
  private function applyEvent(array $row): string {
    $siteId = (string) $row['site_id'];
    $eventId = (int) $row['id'];
    $tx = $this->database->startTransaction();
    try {
      // Re-check under the transaction so two overlapping consumers (lock
      // expiry, manual run during cron) never apply the same event twice.
      $pending = $this->database->select(UsageIngestService::TABLE, 'e')
        ->fields('e', ['id'])
        ->condition('e.id', $eventId)
        ->isNull('e.processed_at')
        ->isNull('e.quarantined_at')
        ->forUpdate()
        ->execute()
        ->fetchField();
      if ($pending === FALSE) {
        unset($tx);
        return 'audited';
      }
      try {
        $event = UsageEventValidator::validate(json_decode((string) $row['payload_json'], TRUE));
        $outcome = $this->project($siteId, $eventId, $event);
      }
      catch (UsageContractException $e) {
        // Ingest already validated this payload; a failure here means the
        // contract changed underneath stored facts. Acknowledge so the
        // consumer does not retry forever, and leave the evidence in place.
        $this->logger->error('Stored usage event @id no longer parses (@code); acknowledged without projection', [
          '@id' => $row['event_id'], '@code' => $e->contractCode,
        ]);
        $outcome = 'skipped';
      }
      $now = $this->nowMs();
      $this->database->update(UsageIngestService::TABLE)
        ->fields(['processed_at' => $now])
        ->condition('id', $eventId)
        ->execute();
      $this->advanceWatermark($siteId, $eventId, (int) $row['occurred_at'], $now);
    }
    catch (\Throwable $e) {
      $tx->rollBack();
      throw $e;
    }
    unset($tx);
    return $outcome;
  }

  /**
   * Updates the projection row and its rollup cells for a validated event.
   *
   * @return string
   *   applied when the projection changed, audited otherwise.
   */
  private function project(string $siteId, int $eventId, array $event): string {
    $attemptId = $event['attempt_id'];
    $revision = (int) $event['observation_revision'];
    $observed = $event['event_type'] === 'attempt.observed';
    $occurredAt = UsageEventValidator::toMilliseconds($event['occurred_at']);

    $current = $this->database->select(self::ATTEMPT_TABLE, 'a')
      ->fields('a')
      ->condition('a.site_id', $siteId)
      ->condition('a.attempt_id', $attemptId)
      ->forUpdate()
      ->execute()
      ->fetchAssoc();
    $current = $current === FALSE ? NULL : $current;

    // Every observation gets its immutable cost entry, including stale ones,
    // so the audit trail is complete even when the projection ignores it.
    $cost = NULL;
    if ($observed && $event['dispatch_state'] !== 'not_sent') {
      $cost = $this->costRating->rateAttempt($siteId, $attemptId, $revision,
        $event['provider'], $event['usage'], $occurredAt);
    }

    if ($current !== NULL) {
      $currentRevision = (int) $current['usage_revision'];
      $stale = $observed ? $revision <= $currentRevision : TRUE;
      if ($stale) {
        $this->logger->info('Usage event for attempt @attempt revision @revision is behind the projection (@current); audited only', [
          '@attempt' => $attemptId, '@revision' => $revision, '@current' => $currentRevision,
        ]);
        return 'audited';
      }
    }

    $fields = $this->projectionFields($siteId, $eventId, $event, $occurredAt, $cost, $current);
    if ($current === NULL) {
      $this->database->insert(self::ATTEMPT_TABLE)->fields($fields)->execute();
    }
    else {
      $this->database->update(self::ATTEMPT_TABLE)
        ->fields($fields)
        ->condition('id', (int) $current['id'])
        ->execute();
    }

    $cells = [$this->cellOf($fields)];
    if ($current !== NULL) {
      $previous = $this->cellOf($current);
      if ($previous !== $cells[0]) {
        $cells[] = $previous;
      }
    }
    foreach ($cells as $cell) {
      $this->recomputeCell($siteId, $cell);
    }
    return 'applied';
  }

  /**
   * Builds the full projection row for an event.
   */
  private function projectionFields(string $siteId, int $eventId, array $event, int $occurredAt,
    ?array $cost, ?array $current): array {
    $observed = $event['event_type'] === 'attempt.observed';
    $context = $event['context'];
    $provider = $event['provider'];
    $timing = $event['timing'];
    $usage = $observed ? $event['usage'] : NULL;
    $quality = $usage['quality'] ?? NULL;
    $reported = $quality === 'reported';
    $resolvedModel = $provider['resolved_model'] ?? $current['resolved_model'] ?? NULL;
    $startedAt = UsageEventValidator::toMilliseconds($timing['started_at']);
    $state = 'prepared';
    if ($observed) {
      // Mirrors the Node store: an abort cannot prove the provider dropped the
      // request, so it is unknown rather than failed.
      $state = match ($event['outcome']) {
        NULL, 'aborted' => 'unknown',
        default => $event['outcome'],
      };
    }
    return [
      'site_id' => $siteId,
      'attempt_id' => $event['attempt_id'],
      'operation_id' => $event['operation_id'],
      'logical_call_id' => $event['logical_call_id'],
      'attempt_no' => (int) $context['attempt_no'],
      'producer_id' => $event['producer_id'],
      'feature' => $context['feature'],
      'stage' => $context['stage'],
      'payer' => $context['payer'],
      'billing_role' => $context['billing_role'],
      'actor_user_id' => $context['actor_user_id'],
      'billing_account_id' => $context['billing_account_id'],
      'provider_account_ref' => $provider['account_ref'],
      'requested_model' => $provider['requested_model'],
      'resolved_model' => $resolvedModel,
      'model_id' => $resolvedModel !== NULL && $resolvedModel !== '' ? $resolvedModel : $provider['requested_model'],
      'gateway_request_id' => $provider['gateway_request_id'] ?? $current['gateway_request_id'] ?? NULL,
      'provider_request_id' => $provider['provider_request_id'] ?? $current['provider_request_id'] ?? NULL,
      'state' => $state,
      'dispatch_state' => $event['dispatch_state'],
      'outcome' => $observed ? $event['outcome'] : NULL,
      'error_code' => $observed ? $event['error_code'] : NULL,
      'usage_revision' => $observed ? (int) $event['observation_revision'] : 0,
      'usage_quality' => $quality,
      'usage_json' => $usage === NULL ? NULL : json_encode($usage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'input_tokens_total' => $reported ? self::count($usage['input_tokens_total']) : NULL,
      'input_tokens_cache_read' => $reported ? self::count($usage['input_tokens_cache_read']) : NULL,
      'input_tokens_cache_write' => $reported ? self::count($usage['input_tokens_cache_write']) : NULL,
      'output_tokens_total' => $reported ? self::count($usage['output_tokens_total']) : NULL,
      'output_tokens_reasoning' => $reported ? self::count($usage['output_tokens_reasoning']) : NULL,
      'cost_valuation_state' => $cost['valuation_state'] ?? NULL,
      'cost_unpriced_reason' => $cost['unpriced_reason'] ?? NULL,
      'cost_currency' => $cost['currency'] ?? NULL,
      'cost_total_micros' => $cost['total_cost_micros'] ?? NULL,
      'started_at' => $startedAt,
      'first_token_at' => self::optionalMs($timing['first_token_at']) ?? ($current['first_token_at'] ?? NULL),
      'finished_at' => self::optionalMs($timing['finished_at']),
      'bucket_start' => intdiv($startedAt, self::HOUR_MS) * self::HOUR_MS,
      'occurred_at' => $occurredAt,
      'last_event_id' => $eventId,
      'projection_version' => self::PROJECTION_VERSION,
      'updated_at' => $this->nowMs(),
    ];
  }

  /**
   * The rollup cell a projection row belongs to.
   *
   * @return array<string,string|int>
   */
  private function cellOf(array $row): array {
    return [
      'bucket_start' => (int) $row['bucket_start'],
      'feature' => (string) $row['feature'],
      'stage' => (string) $row['stage'],
      'payer' => (string) $row['payer'],
      'billing_role' => $row['billing_role'] === NULL || $row['billing_role'] === ''
        ? self::NONE : (string) $row['billing_role'],
      'provider_account_ref' => (string) $row['provider_account_ref'],
      'model_id' => (string) $row['model_id'],
      'actor_user_id' => $row['actor_user_id'] === NULL || $row['actor_user_id'] === ''
        ? self::NONE : (string) $row['actor_user_id'],
      'currency' => $row['cost_currency'] === NULL || $row['cost_currency'] === ''
        ? self::NONE : (string) $row['cost_currency'],
    ];
  }

  /**
   * Recomputes one rollup cell from the projection rows it covers.
   *
   * Attempts are the only grain in this cell, so the aggregate reads a single
   * table; operation and delivery grains get their own cells later rather than
   * a join that would multiply rows.
   */
  private function recomputeCell(string $siteId, array $cell): void {
    $query = $this->database->select(self::ATTEMPT_TABLE, 'a')
      ->condition('a.site_id', $siteId)
      ->condition('a.bucket_start', $cell['bucket_start'])
      ->condition('a.feature', $cell['feature'])
      ->condition('a.stage', $cell['stage'])
      ->condition('a.payer', $cell['payer'])
      ->condition('a.provider_account_ref', $cell['provider_account_ref'])
      ->condition('a.model_id', $cell['model_id']);
    if ($cell['billing_role'] === self::NONE) {
      $query->isNull('a.billing_role');
    }
    else {
      $query->condition('a.billing_role', $cell['billing_role']);
    }
    if ($cell['actor_user_id'] === self::NONE) {
      $query->isNull('a.actor_user_id');
    }
    else {
      $query->condition('a.actor_user_id', $cell['actor_user_id']);
    }
    if ($cell['currency'] === self::NONE) {
      $query->isNull('a.cost_currency');
    }
    else {
      $query->condition('a.cost_currency', $cell['currency']);
    }
    $query->addExpression('COUNT(*)', 'attempt_count');
    $query->addExpression('MAX(a.occurred_at)', 'data_as_of');
    $query->addExpression(self::countWhere("a.state IN ('prepared', 'in_flight')"), 'open_count');
    foreach (['succeeded', 'failed', 'unknown', 'not_sent'] as $state) {
      $query->addExpression(self::countWhere("a.state = '$state'"), $state . '_count');
    }
    $finished = "a.state IN ('succeeded', 'failed', 'unknown')";
    $query->addExpression(self::countWhere("$finished AND a.usage_quality = 'reported'"),
      'usage_reported_count');
    $query->addExpression(self::countWhere(
      "$finished AND (a.usage_quality IS NULL OR a.usage_quality <> 'reported')"),
      'usage_missing_count');
    foreach (['input_tokens_total', 'input_tokens_cache_read', 'input_tokens_cache_write',
      'output_tokens_total', 'output_tokens_reasoning'] as $column) {
      $query->addExpression("SUM(COALESCE(a.$column, 0))", $column);
    }
    $query->addExpression(self::countWhere(
      "a.cost_valuation_state IN ('rated_estimate', 'reconciled')"), 'cost_rated_count');
    $query->addExpression(self::countWhere("a.cost_valuation_state = 'unpriced'"),
      'cost_unpriced_count');
    $query->addExpression('SUM(COALESCE(a.cost_total_micros, 0))', 'cost_micros');
    $sums = $query->execute()->fetchAssoc();

    $keys = ['site_id' => $siteId] + $cell;
    if ((int) $sums['attempt_count'] === 0) {
      $delete = $this->database->delete(self::ROLLUP_TABLE);
      foreach ($keys as $column => $value) {
        $delete->condition($column, $value);
      }
      $delete->execute();
      return;
    }
    $values = [];
    foreach (['attempt_count', 'open_count', 'succeeded_count', 'failed_count', 'unknown_count',
      'not_sent_count', 'usage_reported_count', 'usage_missing_count', 'input_tokens_total',
      'input_tokens_cache_read', 'input_tokens_cache_write', 'output_tokens_total',
      'output_tokens_reasoning', 'cost_rated_count', 'cost_unpriced_count', 'cost_micros',
      'data_as_of'] as $column) {
      $values[$column] = (int) $sums[$column];
    }
    $values['projection_version'] = self::PROJECTION_VERSION;
    $values['updated_at'] = $this->nowMs();
    $this->database->merge(self::ROLLUP_TABLE)->keys($keys)->fields($values)->execute();
  }

  private function advanceWatermark(string $siteId, int $eventId, int $occurredAt, int $now): void {
    $existing = $this->database->select(self::WATERMARK_TABLE, 'w')
      ->fields('w', ['last_event_id', 'processed_count', 'data_as_of'])
      ->condition('site_id', $siteId)
      ->condition('projection', self::PROJECTION)
      ->forUpdate()
      ->execute()
      ->fetchAssoc();
    $this->database->merge(self::WATERMARK_TABLE)
      ->keys(['site_id' => $siteId, 'projection' => self::PROJECTION])
      ->fields([
        'projection_version' => self::PROJECTION_VERSION,
        'last_event_id' => max($eventId, (int) ($existing['last_event_id'] ?? 0)),
        'processed_count' => (int) ($existing['processed_count'] ?? 0) + 1,
        'data_as_of' => max($occurredAt, (int) ($existing['data_as_of'] ?? 0)),
        'updated_at' => $now,
      ])
      ->execute();
  }

  private static function countWhere(string $predicate): string {
    return "SUM(CASE WHEN $predicate THEN 1 ELSE 0 END)";
  }

  /**
   * Decimal-string count to int; values outside the BIGINT range become NULL.
   */
  private static function count(?string $value): ?int {
    if ($value === NULL || !preg_match('/^\d{1,18}$/', $value)) {
      return NULL;
    }
    return (int) $value;
  }

  private static function optionalMs(?string $timestamp): ?int {
    return $timestamp === NULL ? NULL : UsageEventValidator::toMilliseconds($timestamp);
  }

  private function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

}
