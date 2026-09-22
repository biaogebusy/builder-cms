<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;

/**
 * Read-only usage reports for the authenticated user (UB3.1).
 *
 * Everything is computed from the attempt projection and the delivery facts of
 * one site, filtered to one actor, over a half-open `[from, to)` window. The
 * same rows feed the summary, the time series and the operation list, so the
 * three agree under the same filter and watermark. Supplier cost never leaves
 * this service: users see quantities and completeness, not purchase prices.
 *
 * Operations are not stored yet (the operation table belongs to UB4); an
 * operation here is the group of attempts sharing an `operation_id`, created
 * at its first attempt in the window. Its status is derived from the attempt
 * states: open attempts make it `running`; otherwise its non-auxiliary
 * attempts (all attempts when it has none) decide: an unknown attempt makes it
 * `unknown`, otherwise a succeeded attempt makes it `succeeded`, then `failed`,
 * then `not_sent`. Unknown is never reported as success, and a succeeded
 * auxiliary step never stands in for the primary call.
 */
final class UsageReportService {

  public const DEFAULT_RANGE_MS = 30 * 86_400_000;
  /** Longest window per granularity; longer reports go through exports later. */
  public const MAX_RANGE_MS = ['hour' => 31 * 86_400_000, 'day' => 366 * 86_400_000];
  /** Attempts one request may scan; beyond that the window must shrink. */
  public const MAX_ATTEMPTS = 20_000;
  public const DEFAULT_LIMIT = 50;
  public const MAX_LIMIT = 100;
  public const GRANULARITIES = ['hour', 'day'];
  public const STATUSES = ['running', 'unknown', 'succeeded', 'failed', 'not_sent'];
  private const OPEN = ['prepared', 'in_flight'];
  private const FINISHED = ['succeeded', 'failed', 'unknown'];
  private const TOKENS = ['input_tokens_total', 'input_tokens_cache_read', 'input_tokens_cache_write',
    'output_tokens_total', 'output_tokens_reasoning'];
  private const LABEL = '/^[A-Za-z0-9_.:-]{1,64}\z/';
  private const ISO = '/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?)?(?:Z|[+-]\d{2}:\d{2})?\z/';
  private const IN_CHUNK = 500;

  public function __construct(
    private readonly Connection $database,
    private readonly UsageProjectionService $projection,
  ) {}

  /**
   * Normalizes the query filter shared by every report.
   *
   * @param array $query
   *   `from`, `to` (ISO 8601; a value without zone is read in `timezone`),
   *   `timezone` (IANA, default UTC), `feature`, `model`, `granularity`.
   * @param int $nowMs
   *   The request time; the default window is the 30 days ending here.
   * @param string $granularity
   *   Selects the maximum window; `day` for reports without buckets.
   *
   * @return array{from:int,to:int,timezone:\DateTimeZone,feature:?string,model:?string}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   *   `invalid_request` for unusable values, `range_too_large` beyond the limit.
   */
  public function parseFilter(array $query, int $nowMs, string $granularity = 'day'): array {
    if (!in_array($granularity, self::GRANULARITIES, TRUE)) {
      throw new UsageReportException('invalid_request', 'granularity must be hour or day');
    }
    $timezoneName = self::text($query, 'timezone') ?? 'UTC';
    try {
      $timezone = new \DateTimeZone($timezoneName);
    }
    catch (\Throwable $e) {
      throw new UsageReportException('invalid_request', "unknown timezone {$timezoneName}", $e);
    }
    $to = self::instant($query, 'to', $timezone) ?? $nowMs;
    $from = self::instant($query, 'from', $timezone) ?? ($to - self::DEFAULT_RANGE_MS);
    if ($from >= $to) {
      throw new UsageReportException('invalid_request', 'from must be before to');
    }
    if ($to - $from > self::MAX_RANGE_MS[$granularity]) {
      throw new UsageReportException('range_too_large', sprintf('the window may span at most %d days at %s granularity',
        intdiv(self::MAX_RANGE_MS[$granularity], 86_400_000), $granularity));
    }
    $filter = [];
    foreach (['feature', 'model'] as $key) {
      $value = self::text($query, $key);
      if ($value !== NULL && !preg_match(self::LABEL, $value)) {
        throw new UsageReportException('invalid_request', "$key must be a label of at most 64 characters");
      }
      $filter[$key] = $value;
    }
    return ['from' => $from, 'to' => $to, 'timezone' => $timezone] + $filter;
  }

  /**
   * Quantities and completeness of the user's usage in the window.
   */
  public function summary(string $siteId, string $actorUserId, array $filter): array {
    $rows = $this->attempts($siteId, $actorUserId, $filter);
    $totals = self::aggregate($rows);
    $totals['operations'] = count(array_unique(array_column($rows, 'operation_id')));
    $totals['images_delivered'] = $this->deliveredCount($siteId, array_unique(array_column($rows, 'operation_id')));
    return [
      'filter' => $this->describeFilter($filter),
      'totals' => $totals,
      // No sales price book or ledger exists yet (UB4); nothing was charged.
      'charges' => ['status' => 'not_enabled'],
    ] + $this->completeness($siteId);
  }

  /**
   * Continuous buckets over the window in the requested time zone.
   *
   * Buckets are cut on wall-clock boundaries of `timezone`; a bucket without
   * attempts is present with `empty: true` and zero counts so a chart never
   * mistakes a gap for missing data.
   */
  public function timeseries(string $siteId, string $actorUserId, array $filter, string $granularity): array {
    if (!in_array($granularity, self::GRANULARITIES, TRUE)) {
      throw new UsageReportException('invalid_request', 'granularity must be hour or day');
    }
    $rows = $this->attempts($siteId, $actorUserId, $filter);
    $tz = $filter['timezone'];
    $format = $granularity === 'hour' ? 'Y-m-d\TH:00:00P' : 'Y-m-d\T00:00:00P';
    $grouped = [];
    foreach ($rows as $row) {
      $key = self::at((int) $row['started_at'], $tz)->format($format);
      $grouped[$key][] = $row;
    }
    $buckets = [];
    $cursor = self::floor(self::at($filter['from'], $tz), $granularity);
    $end = self::at($filter['to'], $tz);
    while ($cursor < $end) {
      $key = $cursor->format($format);
      $bucketRows = $grouped[$key] ?? [];
      $buckets[] = ['bucket_start' => $key, 'empty' => $bucketRows === [],
        'operations' => count(array_unique(array_column($bucketRows, 'operation_id')))]
        + self::aggregate($bucketRows);
      $cursor = $cursor->modify($granularity === 'hour' ? '+1 hour' : '+1 day');
    }
    return [
      'filter' => $this->describeFilter($filter) + ['granularity' => $granularity],
      'buckets' => $buckets,
    ] + $this->completeness($siteId);
  }

  /**
   * The user's operations in the window, newest first, by `(created_at, operation_id)` cursor.
   *
   * @return array{filter:array,operations:list<array>,next_cursor:?string,limit:int}
   */
  public function operations(string $siteId, string $actorUserId, array $filter, ?string $cursor, int $limit,
    ?string $status = NULL): array {
    $limit = max(1, min($limit, self::MAX_LIMIT));
    if ($status !== NULL && !in_array($status, self::STATUSES, TRUE)) {
      throw new UsageReportException('invalid_request', 'status must be one of ' . implode(', ', self::STATUSES));
    }
    $query = $this->scoped($siteId, $actorUserId, $filter)
      ->fields('a', ['operation_id'])
      ->groupBy('a.operation_id')
      ->range(0, $limit + 1);
    $query->addExpression('MIN(a.started_at)', 'created_at');
    $query->addExpression(self::countWhere("a.state IN ('prepared', 'in_flight')"), 'open_count');
    foreach (['succeeded', 'failed', 'unknown', 'not_sent'] as $state) {
      $query->addExpression(self::countWhere("a.state = '$state'"), $state . '_count');
    }
    $query->orderBy('created_at', 'DESC')->orderBy('a.operation_id', 'DESC');
    if ($cursor !== NULL) {
      [$cursorTs, $cursorId] = self::decodeCursor($cursor);
      $query->having('(MIN(a.started_at) < :cursor_ts OR (MIN(a.started_at) = :cursor_ts AND a.operation_id < :cursor_id))',
        [':cursor_ts' => $cursorTs, ':cursor_id' => $cursorId]);
    }
    if ($status !== NULL) {
      $query->having(self::statusHaving($status));
    }
    $heads = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $next = NULL;
    if (count($heads) > $limit) {
      array_pop($heads);
      $last = end($heads);
      $next = self::encodeCursor((int) $last['created_at'], (string) $last['operation_id']);
    }
    $ids = array_column($heads, 'operation_id');
    $byOperation = [];
    foreach ($this->attemptsOf($siteId, $actorUserId, $ids, $filter) as $row) {
      $byOperation[$row['operation_id']][] = $row;
    }
    $delivered = $this->deliveredByOperation($siteId, $ids);
    $operations = [];
    foreach ($heads as $head) {
      $id = (string) $head['operation_id'];
      $operations[] = $this->describeOperation($id, $byOperation[$id] ?? [], $delivered[$id] ?? 0, $filter['timezone']);
    }
    return [
      'filter' => $this->describeFilter($filter) + ['status' => $status],
      'operations' => $operations,
      'next_cursor' => $next,
      'limit' => $limit,
    ] + $this->completeness($siteId);
  }

  /**
   * One operation of the user with its attempts and deliveries, or NULL when not theirs.
   */
  public function operation(string $siteId, string $actorUserId, string $operationId, \DateTimeZone $tz): ?array {
    $rows = $this->attemptsOf($siteId, $actorUserId, [$operationId], NULL);
    if ($rows === []) {
      return NULL;
    }
    $deliveries = $this->database->select(LocalUsageProducer::DELIVERY_TABLE, 'd')
      ->fields('d', ['attempt_id', 'output_index', 'artifact_kind', 'artifact_ref', 'state', 'delivered_at'])
      ->condition('d.site_id', $siteId)
      ->condition('d.operation_id', $operationId)
      ->orderBy('d.attempt_id')->orderBy('d.output_index')->orderBy('d.id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $committed = count(array_filter($deliveries, static fn(array $d): bool =>
      $d['state'] === LocalUsageProducer::STATE_COMMITTED && $d['artifact_kind'] === 'image_asset'));
    // The summary block already carries `attempts` as a count; the list gets
    // its own key so the union cannot swallow it.
    return $this->describeOperation($operationId, $rows, $committed, $tz) + [
      'attempt_list' => array_map(fn(array $row): array => $this->describeAttempt($row, $tz), $rows),
      'deliveries' => array_map(static fn(array $d): array => [
        'attempt_id' => $d['attempt_id'],
        'output_index' => (int) $d['output_index'],
        'artifact_kind' => $d['artifact_kind'],
        'artifact_ref' => $d['artifact_ref'],
        'state' => $d['state'],
        'delivered_at' => self::iso((int) $d['delivered_at'], $tz),
      ], $deliveries),
      // No sales price book or ledger exists yet (UB4); nothing was charged.
      'charges' => ['status' => 'not_enabled'],
    ] + $this->completeness($siteId);
  }

  private function scoped(string $siteId, string $actorUserId, ?array $filter) {
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->condition('a.site_id', $siteId)
      ->condition('a.actor_user_id', $actorUserId);
    if ($filter !== NULL) {
      $query->condition('a.started_at', $filter['from'], '>=')
        ->condition('a.started_at', $filter['to'], '<');
      if ($filter['feature'] !== NULL) {
        $query->condition('a.feature', $filter['feature']);
      }
      if ($filter['model'] !== NULL) {
        $query->condition('a.model_id', $filter['model']);
      }
    }
    return $query;
  }

  /**
   * @return list<array<string,mixed>>
   */
  private function attempts(string $siteId, string $actorUserId, array $filter): array {
    $count = (int) $this->scoped($siteId, $actorUserId, $filter)->countQuery()->execute()->fetchField();
    if ($count > self::MAX_ATTEMPTS) {
      throw new UsageReportException('range_too_large', sprintf('the window covers %d attempts; narrow it to at most %d',
        $count, self::MAX_ATTEMPTS));
    }
    return $this->scoped($siteId, $actorUserId, $filter)
      ->fields('a')
      ->orderBy('a.started_at')->orderBy('a.id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @return list<array<string,mixed>>
   */
  private function attemptsOf(string $siteId, string $actorUserId, array $operationIds, ?array $filter): array {
    if ($operationIds === []) {
      return [];
    }
    $rows = [];
    foreach (array_chunk(array_values($operationIds), self::IN_CHUNK) as $chunk) {
      $rows = [...$rows, ...$this->scoped($siteId, $actorUserId, $filter)
        ->fields('a')
        ->condition('a.operation_id', $chunk, 'IN')
        ->orderBy('a.started_at')->orderBy('a.id')
        ->execute()->fetchAll(\PDO::FETCH_ASSOC)];
    }
    return $rows;
  }

  private function deliveredCount(string $siteId, array $operationIds): int {
    return array_sum($this->deliveredByOperation($siteId, $operationIds));
  }

  /**
   * Committed image assets per operation: the deliveries a user may be charged for.
   *
   * @return array<string,int>
   */
  private function deliveredByOperation(string $siteId, array $operationIds): array {
    $counts = [];
    foreach (array_chunk(array_values($operationIds), self::IN_CHUNK) as $chunk) {
      if ($chunk === []) {
        continue;
      }
      $query = $this->database->select(LocalUsageProducer::DELIVERY_TABLE, 'd')
        ->fields('d', ['operation_id'])
        ->condition('d.site_id', $siteId)
        ->condition('d.operation_id', $chunk, 'IN')
        ->condition('d.state', LocalUsageProducer::STATE_COMMITTED)
        ->condition('d.artifact_kind', 'image_asset')
        ->groupBy('d.operation_id');
      $query->addExpression('COUNT(*)', 'delivered');
      foreach ($query->execute() as $row) {
        $counts[(string) $row->operation_id] = (int) $row->delivered;
      }
    }
    return $counts;
  }

  /**
   * Counts and sums of a set of attempt rows; tokens are decimal strings.
   *
   * @return array<string,mixed>
   */
  private static function aggregate(array $rows): array {
    $counts = ['attempts' => 0, 'sent' => 0, 'open' => 0, 'succeeded' => 0, 'failed' => 0, 'unknown' => 0,
      'not_sent' => 0, 'usage_reported' => 0, 'usage_missing' => 0];
    $sums = array_fill_keys([...self::TOKENS, 'images_generated'], 0);
    foreach ($rows as $row) {
      $counts['attempts']++;
      if ($row['dispatch_state'] === 'sent') {
        $counts['sent']++;
      }
      $state = (string) $row['state'];
      if (in_array($state, self::OPEN, TRUE)) {
        $counts['open']++;
      }
      elseif (isset($counts[$state])) {
        $counts[$state]++;
      }
      $reported = $row['usage_quality'] === 'reported';
      if (in_array($state, self::FINISHED, TRUE)) {
        $counts[$reported ? 'usage_reported' : 'usage_missing']++;
      }
      if ($reported) {
        foreach (array_keys($sums) as $column) {
          $sums[$column] += (int) ($row[$column] ?? 0);
        }
      }
    }
    return $counts + [
      'tokens' => array_map('strval', array_intersect_key($sums, array_flip(self::TOKENS))),
      'images_generated' => (string) $sums['images_generated'],
    ];
  }

  private function describeOperation(string $id, array $rows, int $delivered, \DateTimeZone $tz): array {
    $first = $rows[0] ?? NULL;
    $primary = NULL;
    foreach ($rows as $row) {
      if ($row['billing_role'] === 'primary') {
        $primary = $row;
        break;
      }
    }
    $lead = $primary ?? $first;
    $startedAt = $first === NULL ? NULL : (int) $first['started_at'];
    $updatedAt = $rows === [] ? NULL : max(array_map('intval', array_column($rows, 'occurred_at')));
    return [
      'operation_id' => $id,
      'created_at' => $startedAt === NULL ? NULL : self::iso($startedAt, $tz),
      'updated_at' => $updatedAt === NULL ? NULL : self::iso($updatedAt, $tz),
      'feature' => $lead['feature'] ?? NULL,
      'stage' => $lead['stage'] ?? NULL,
      'producer_id' => $lead['producer_id'] ?? NULL,
      'payer' => $lead['payer'] ?? NULL,
      'models' => array_values(array_unique(array_column($rows, 'model_id'))),
      'status' => self::operationStatus($rows),
      'images_delivered' => $delivered,
    ] + self::aggregate($rows);
  }

  private function describeAttempt(array $row, \DateTimeZone $tz): array {
    $reported = $row['usage_quality'] === 'reported';
    return [
      'attempt_id' => $row['attempt_id'],
      'logical_call_id' => $row['logical_call_id'],
      'attempt_no' => (int) $row['attempt_no'],
      'stage' => $row['stage'],
      'feature' => $row['feature'],
      'billing_role' => $row['billing_role'],
      'payer' => $row['payer'],
      'producer_id' => $row['producer_id'],
      'requested_model' => $row['requested_model'],
      'model' => $row['model_id'],
      'state' => $row['state'],
      'dispatch_state' => $row['dispatch_state'],
      'error_code' => $row['error_code'],
      'usage_quality' => $row['usage_quality'],
      'tokens' => $reported ? array_map(static fn($value) => $value === NULL ? NULL : (string) $value,
        array_intersect_key($row, array_flip(self::TOKENS))) : NULL,
      'images_generated' => $reported && $row['images_generated'] !== NULL ? (string) $row['images_generated'] : NULL,
      'started_at' => self::iso((int) $row['started_at'], $tz),
      'finished_at' => $row['finished_at'] === NULL ? NULL : self::iso((int) $row['finished_at'], $tz),
    ];
  }

  private static function operationStatus(array $rows): string {
    if (array_intersect(array_column($rows, 'state'), self::OPEN) !== []) {
      return 'running';
    }
    // Only the calls that deliver the operation decide its outcome. A succeeded
    // auxiliary step (a prompt rewrite ordered under an image job, UB2.6) must
    // not turn a failed generation into a success; an operation made of
    // auxiliary calls alone (a legacy title request) is judged on those.
    $deciding = array_filter($rows, static fn(array $row): bool => ($row['billing_role'] ?? NULL) !== 'auxiliary') ?: $rows;
    $states = array_column($deciding, 'state');
    foreach (['unknown', 'succeeded', 'failed'] as $state) {
      if (in_array($state, $states, TRUE)) {
        return $state;
      }
    }
    return 'not_sent';
  }

  /**
   * The HAVING clause selecting operations of one derived status.
   *
   * Mirrors operationStatus() so the list filter and the reported status agree:
   * an open attempt of any role is `running`; otherwise the non-auxiliary
   * attempts decide, falling back to all attempts when there are none.
   */
  private static function statusHaving(string $status): string {
    $open = self::countWhere("a.state IN ('prepared', 'in_flight')");
    if ($status === 'running') {
      return "$open > 0";
    }
    $outcome = static function (bool $deciding) use ($status): string {
      $scope = $deciding ? " AND (a.billing_role IS NULL OR a.billing_role <> 'auxiliary')" : '';
      $count = static fn(string $predicate): string => self::countWhere("($predicate)$scope");
      return match ($status) {
        'unknown' => "{$count("a.state = 'unknown'")} > 0",
        'succeeded' => "{$count("a.state = 'unknown'")} = 0 AND {$count("a.state = 'succeeded'")} > 0",
        'failed' => "{$count("a.state IN ('unknown', 'succeeded')")} = 0 AND {$count("a.state = 'failed'")} > 0",
        'not_sent' => "{$count("a.state <> 'not_sent'")} = 0",
      };
    };
    $deciding = self::countWhere("a.billing_role IS NULL OR a.billing_role <> 'auxiliary'");
    return "$open = 0 AND (($deciding > 0 AND {$outcome(TRUE)}) OR ($deciding = 0 AND {$outcome(FALSE)}))";
  }

  /**
   * The consumer watermark: how fresh the projection every report reads is.
   */
  private function completeness(string $siteId): array {
    $watermark = $this->projection->watermark($siteId);
    return [
      'data_as_of' => $watermark === NULL ? NULL : self::iso($watermark['data_as_of'], new \DateTimeZone('UTC')),
      'projection_version' => $watermark['projection_version'] ?? NULL,
      'pending_events' => $this->projection->pendingCount($siteId),
    ];
  }

  private function describeFilter(array $filter): array {
    return [
      'from' => self::iso($filter['from'], $filter['timezone']),
      'to' => self::iso($filter['to'], $filter['timezone']),
      'timezone' => $filter['timezone']->getName(),
      'feature' => $filter['feature'],
      'model' => $filter['model'],
    ];
  }

  private static function instant(array $query, string $key, \DateTimeZone $tz): ?int {
    $value = self::text($query, $key);
    if ($value === NULL) {
      return NULL;
    }
    if (!preg_match(self::ISO, $value)) {
      throw new UsageReportException('invalid_request', "$key must be an ISO 8601 date or date-time");
    }
    try {
      $parsed = new \DateTimeImmutable($value, $tz);
    }
    catch (\Throwable $e) {
      throw new UsageReportException('invalid_request', "$key is not a valid date-time", $e);
    }
    return (int) $parsed->format('Uv');
  }

  private static function text(array $query, string $key): ?string {
    $value = $query[$key] ?? NULL;
    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

  private static function at(int $ms, \DateTimeZone $tz): \DateTimeImmutable {
    return (new \DateTimeImmutable('@' . intdiv($ms, 1000)))->setTimezone($tz)
      ->modify(sprintf('+%d milliseconds', $ms % 1000));
  }

  private static function floor(\DateTimeImmutable $time, string $granularity): \DateTimeImmutable {
    return $granularity === 'hour' ? $time->setTime((int) $time->format('G'), 0) : $time->setTime(0, 0);
  }

  private static function iso(int $ms, \DateTimeZone $tz): string {
    return self::at($ms, $tz)->format('Y-m-d\TH:i:s.vP');
  }

  private static function encodeCursor(int $createdAt, string $operationId): string {
    return rtrim(strtr(base64_encode($createdAt . ':' . $operationId), '+/', '-_'), '=');
  }

  /**
   * @return array{0:int,1:string}
   */
  private static function decodeCursor(string $cursor): array {
    $decoded = base64_decode(strtr($cursor, '-_', '+/'), TRUE);
    if ($decoded === FALSE || !preg_match('/^(\d{1,15}):(.{1,128})\z/', $decoded, $m)) {
      throw new UsageReportException('invalid_request', 'cursor is not valid');
    }
    return [(int) $m[1], $m[2]];
  }

  private static function countWhere(string $predicate): string {
    return "SUM(CASE WHEN $predicate THEN 1 ELSE 0 END)";
  }

}
