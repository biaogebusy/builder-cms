<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;

/**
 * Data-quality report and work queue of one site's attempt projection (UB3.5a).
 *
 * Read-only: this service never writes a verdict back; it names the entry that
 * should (Drush reconcile, the Node reconcile route, the price book form,
 * release-quarantined) and leaves the write to those.
 *
 * Three families cover the ways a projected attempt can be incomplete:
 *
 * - incomplete facts: unknown outcome, or an intent open past the stuck
 *   threshold that neither producer's close-out has settled;
 * - unknown usage: a sent call whose reported usage is missing or invalid;
 * - unpriced: no cost figure, split by the reason the rating service recorded.
 *
 * Families count their own members, so one attempt hitting several families
 * appears in each; the queue deduplicates them with the priority
 * A (incomplete) > B (usage) > C (unpriced) and labels every attempt with one
 * `primary_issue`, so the queue length is the real number of open work items.
 * Unpriced reasons whose root cause is family B (missing_usage, invalid_usage)
 * are listed but never queued, and customer_key calls are not platform cost by
 * design, so they are neither queued nor counted as actionable.
 *
 * Two problem shapes have no attempt row at all: attempt numbers missing from
 * a logical call (events that never arrived) and quarantined events (facts the
 * projection could not apply). They are bounded side lists, not queue rows.
 *
 * Everything runs as aggregate SQL over the report scope; no endpoint of this
 * service materializes the window, so the 20,000-attempt report scan cap does
 * not apply. Window bounds still come from parseFilter, including its
 * `range_too_large` limit.
 */
final class UsageQualityReportService {

  /** Work-queue issues in deduplication priority order. */
  public const ISSUES = ['unknown_attempt', 'stuck_intent', 'usage_missing', 'usage_invalid', 'unpriced'];
  /** Unpriced reasons an operator can resolve without external data. */
  public const ACTIONABLE_REASONS = ['no_price_book', 'unknown_model', 'token_rate_missing',
    'invalid_price_book', 'cost_overflow'];
  /** Unpriced reasons whose root cause is the unknown-usage family; never queued separately. */
  public const BLOCKED_REASONS = ['missing_usage', 'invalid_usage'];
  /** Calls on the customer's own key are not platform cost by design; never queued. */
  public const BY_DESIGN_REASONS = ['customer_key'];
  public const DEFAULT_STUCK_MINUTES = 60;
  public const MIN_STUCK_MINUTES = 5;
  public const MAX_STUCK_MINUTES = 1440;
  public const DEFAULT_ITEMS_LIMIT = 50;
  public const MAX_ITEMS_LIMIT = 200;
  /** Bounded detail lists: attempt gaps and quarantined events. */
  public const LIST_LIMIT = 50;
  /**
   * Missing attempt numbers enumerated per logical call; a corrupted huge
   * attempt_no stops the enumeration here instead of walking its whole range.
   */
  public const MAX_MISSING_NUMBERS = 1000;
  /** Gap candidates re-checked per report; each costs one indexed query. */
  public const MAX_GAP_CANDIDATES = 200;
  /** Distinct quarantine error messages kept in the distribution. */
  public const REASON_LIMIT = 20;
  private const MINUTE_MS = 60_000;

  public function __construct(
    private readonly Connection $database,
    private readonly UsageProjectionService $projection,
    private readonly SiteUsageReportService $siteReports,
  ) {}

  /**
   * The quality report of one window: families, deduplicated queue, pipeline.
   *
   * @param bool $withCosts
   *   Whether the account may see supplier costs; without it the unpriced
   *   family and unpriced queue rows are omitted entirely.
   *
   * @return array{filter:array,families:array,queue:array,pipeline:array,attempt_gaps:array,quarantined_events:array,data_as_of:string|null,projection_version:int|null}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   */
  public function quality(string $siteId, array $query, bool $withCosts, int $nowMs): array {
    $filter = $this->siteReports->parseFilter($query, $nowMs);
    $stuckCutoff = $nowMs - $this->stuckMinutes($query) * self::MINUTE_MS;

    $families = [
      'incomplete' => $this->incompleteFamily($siteId, $filter, $stuckCutoff),
      'usage_unknown' => $this->usageUnknownFamily($siteId, $filter),
    ];
    if ($withCosts) {
      $families['unpriced'] = $this->unpricedFamily($siteId, $filter);
    }

    return [
      'filter' => $this->describeFilter($filter, $query),
      'families' => $families,
      'queue' => $this->queue($siteId, $filter, $families, $withCosts, $stuckCutoff),
      'pipeline' => $this->pipeline($siteId, $nowMs),
      'attempt_gaps' => $this->attemptGaps($siteId, $filter),
      'quarantined_events' => $this->quarantinedEvents($siteId),
    ] + $this->envelope($siteId);
  }

  /**
   * The deduplicated work queue: one row per attempt, newest problem first.
   *
   * @param bool $withCosts
   *   As quality(); an `unpriced` issue filter without it is a refusal.
   *
   * @return array{filter:array,issue:string|null,total:int,limit:int,truncated:bool,next_cursor:string|null,rows:list<array>,data_as_of:string|null,projection_version:int|null}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   *   `invalid_request` for an unknown issue or an unusable cursor,
   *   `forbidden` for `issue=unpriced` without supplier-costs permission.
   */
  public function items(string $siteId, array $query, bool $withCosts, int $nowMs): array {
    $filter = $this->siteReports->parseFilter($query, $nowMs);
    $stuckCutoff = $nowMs - $this->stuckMinutes($query) * self::MINUTE_MS;
    $issue = self::text($query, 'issue');
    if ($issue !== NULL && !in_array($issue, self::ISSUES, TRUE)) {
      throw new UsageReportException('invalid_request', 'issue must be one of ' . implode(', ', self::ISSUES));
    }
    if ($issue === 'unpriced' && !$withCosts) {
      throw new UsageReportException('forbidden', 'the unpriced queue requires the view ai supplier costs permission');
    }
    $limit = self::intInRange($query, 'limit', 1, self::MAX_ITEMS_LIMIT, self::DEFAULT_ITEMS_LIMIT, 'limit');
    $cursor = $this->parseCursor($query);

    $case = $this->primaryIssueSql($withCosts, $stuckCutoff);
    $select = $this->siteReports->scoped($siteId, $filter);
    $select->fields('a', ['attempt_id', 'operation_id', 'producer_id', 'feature', 'stage',
      'logical_call_id', 'attempt_no', 'actor_user_id', 'provider_account_ref', 'model_id',
      'state', 'dispatch_state', 'usage_quality', 'error_code', 'gateway_request_id',
      'cost_valuation_state', 'cost_unpriced_reason', 'started_at', 'updated_at']);
    $select->where($issue === NULL ? "$case IS NOT NULL" : "$case = '" . $issue . "'");
    if ($cursor !== NULL) {
      // Two distinct placeholders per value: a repeated named placeholder is
      // not portable across drivers.
      $and = $select->andConditionGroup()
        ->condition('a.started_at', $cursor[0])
        ->condition('a.attempt_id', $cursor[1], '<');
      $select->condition($select->orConditionGroup()
        ->condition('a.started_at', $cursor[0], '<')
        ->condition($and));
    }
    $select->orderBy('a.started_at', 'DESC')
      ->orderBy('a.attempt_id', 'DESC')
      ->range(0, $limit + 1);
    $rows = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $truncated = count($rows) > $limit;
    if ($truncated) {
      array_pop($rows);
    }

    $count = $this->siteReports->scoped($siteId, $filter);
    $count->where($issue === NULL ? "$case IS NOT NULL" : "$case = '" . $issue . "'");
    $count->addExpression('COUNT(*)', 'c');
    $total = (int) $count->execute()->fetchField();

    $items = [];
    foreach ($rows as $row) {
      $primary = $this->classify($row, $withCosts, $stuckCutoff);
      $items[] = [
        'attempt_id' => (string) $row['attempt_id'],
        'operation_id' => (string) $row['operation_id'],
        'producer_id' => (string) $row['producer_id'],
        'feature' => (string) $row['feature'],
        'stage' => (string) $row['stage'],
        'logical_call_id' => (string) $row['logical_call_id'],
        'attempt_no' => (int) $row['attempt_no'],
        'actor_user_id' => $row['actor_user_id'] === NULL ? NULL : (string) $row['actor_user_id'],
        'provider_account_ref' => (string) $row['provider_account_ref'],
        'model_id' => (string) $row['model_id'],
        'state' => (string) $row['state'],
        'dispatch_state' => (string) $row['dispatch_state'],
        'usage_quality' => $row['usage_quality'] === NULL ? NULL : (string) $row['usage_quality'],
        'error_code' => $row['error_code'] === NULL ? NULL : (string) $row['error_code'],
        'gateway_request_id' => $row['gateway_request_id'] === NULL ? NULL : (string) $row['gateway_request_id'],
        'cost_unpriced_reason' => $withCosts && $row['cost_unpriced_reason'] !== NULL
          ? (string) $row['cost_unpriced_reason'] : NULL,
        'started_at' => ReportTime::iso((int) $row['started_at'], $filter['timezone']),
        'updated_at' => ReportTime::iso((int) $row['updated_at'], $filter['timezone']),
        'primary_issue' => $primary,
        'resolution' => $this->resolution($primary, (string) $row['producer_id']),
      ];
    }

    $last = $rows === [] ? NULL : array_key_last($rows);
    return [
      'filter' => $this->describeFilter($filter, $query),
      'issue' => $issue,
      'total' => $total,
      'limit' => $limit,
      'truncated' => $truncated,
      'next_cursor' => $truncated && $last !== NULL
        ? sprintf('%d,%s', (int) $rows[$last]['started_at'], $rows[$last]['attempt_id'])
        : NULL,
      'rows' => $items,
    ] + $this->envelope($siteId);
  }

  /**
   * Family A: attempts whose facts are incomplete.
   *
   * @return array{unknown_attempt:array{count:int,oldest_started_at:string|null},stuck_intent:array{count:int,oldest_started_at:string|null},by_producer:list<array>,by_model:list<array>}
   */
  private function incompleteFamily(string $siteId, array $filter, int $stuckCutoff): array {
    $unknown = "a.state = 'unknown'";
    $stuck = sprintf("(a.state IN ('prepared', 'in_flight') AND a.started_at <= %d)", $stuckCutoff);

    $totals = $this->siteReports->scoped($siteId, $filter);
    $totals->where("($unknown) OR $stuck");
    $totals->addExpression("SUM(CASE WHEN $unknown THEN 1 ELSE 0 END)", 'unknown_count');
    $totals->addExpression("SUM(CASE WHEN $stuck THEN 1 ELSE 0 END)", 'stuck_count');
    $totals->addExpression("MIN(CASE WHEN $unknown THEN a.started_at END)", 'unknown_oldest');
    $totals->addExpression("MIN(CASE WHEN $stuck THEN a.started_at END)", 'stuck_oldest');
    $row = $totals->execute()->fetchAssoc() ?: [];

    $split = $this->siteReports->scoped($siteId, $filter);
    $split->where("($unknown) OR $stuck");
    $split->fields('a', ['producer_id', 'model_id']);
    $split->addExpression("SUM(CASE WHEN $unknown THEN 1 ELSE 0 END)", 'unknown_count');
    $split->addExpression("SUM(CASE WHEN $stuck THEN 1 ELSE 0 END)", 'stuck_count');
    $split->groupBy('a.producer_id');
    $split->groupBy('a.model_id');
    [$byProducer, $byModel] = $this->pivotSplit($split->execute()->fetchAll(\PDO::FETCH_ASSOC), 'unknown_count', 'stuck_count');

    return [
      'unknown_attempt' => ['count' => (int) ($row['unknown_count'] ?? 0),
        'oldest_started_at' => $this->optionalIso($row['unknown_oldest'] ?? NULL, $filter)],
      'stuck_intent' => ['count' => (int) ($row['stuck_count'] ?? 0),
        'oldest_started_at' => $this->optionalIso($row['stuck_oldest'] ?? NULL, $filter)],
      'by_producer' => $byProducer,
      'by_model' => $byModel,
    ];
  }

  /**
   * Family B: sent calls whose reported usage is missing or invalid.
   *
   * @return array{missing:array{count:int,oldest_started_at:string|null},invalid:array{count:int,oldest_started_at:string|null},by_model:list<array>,by_account:list<array>}
   */
  private function usageUnknownFamily(string $siteId, array $filter): array {
    $base = "a.dispatch_state = 'sent' AND a.usage_quality IN ('missing', 'invalid')";

    $totals = $this->siteReports->scoped($siteId, $filter);
    $totals->where($base);
    $totals->fields('a', ['usage_quality']);
    $totals->addExpression('COUNT(*)', 'count');
    $totals->addExpression('MIN(a.started_at)', 'oldest');
    $totals->groupBy('a.usage_quality');
    $counts = [];
    foreach ($totals->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $counts[(string) $row['usage_quality']] = $row;
    }

    $split = $this->siteReports->scoped($siteId, $filter);
    $split->where($base);
    $split->fields('a', ['usage_quality', 'model_id', 'provider_account_ref']);
    $split->addExpression('COUNT(*)', 'attempts');
    $split->groupBy('a.usage_quality');
    $split->groupBy('a.model_id');
    $split->groupBy('a.provider_account_ref');
    $byModel = [];
    $byAccount = [];
    foreach ($split->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $quality = (string) $row['usage_quality'];
      $byModel[$row['model_id']][$quality] = (int) $row['attempts'] + ($byModel[$row['model_id']][$quality] ?? 0);
      $byAccount[$row['provider_account_ref']][$quality] = (int) $row['attempts']
        + ($byAccount[$row['provider_account_ref']][$quality] ?? 0);
    }

    // Sort every split by total problems first, then by its key.
    $shape = static function (array $map, string $key): array {
      $rows = [];
      foreach ($map as $value => $counts) {
        $rows[] = [$key => (string) $value, 'missing' => $counts['missing'] ?? 0,
          'invalid' => $counts['invalid'] ?? 0];
      }
      usort($rows, static fn(array $a, array $b): int =>
        [($b['missing'] + $b['invalid']), $a[$key]] <=> [($a['missing'] + $a['invalid']), $b[$key]]);
      return $rows;
    };
    return [
      'missing' => ['count' => (int) ($counts['missing']['count'] ?? 0),
        'oldest_started_at' => $this->optionalIso($counts['missing']['oldest'] ?? NULL, $filter)],
      'invalid' => ['count' => (int) ($counts['invalid']['count'] ?? 0),
        'oldest_started_at' => $this->optionalIso($counts['invalid']['oldest'] ?? NULL, $filter)],
      'by_model' => $shape($byModel, 'model'),
      'by_account' => $shape($byAccount, 'account'),
    ];
  }

  /**
   * Family C: unpriced attempts grouped by the reason the rating recorded.
   *
   * @return array{by_reason:list<array>,actionable_total:int,blocked_by_usage_total:int,by_design_total:int}
   */
  private function unpricedFamily(string $siteId, array $filter): array {
    $query = $this->siteReports->scoped($siteId, $filter);
    $query->condition('a.cost_valuation_state', CostRatingService::STATE_UNPRICED);
    $query->fields('a', ['cost_unpriced_reason']);
    $query->addExpression('COUNT(*)', 'attempts');
    $query->addExpression('MIN(a.started_at)', 'oldest');
    $query->groupBy('a.cost_unpriced_reason');
    $query->orderBy('attempts', 'DESC');
    $query->orderBy('a.cost_unpriced_reason');

    $rows = [];
    $actionable = $blocked = $byDesign = 0;
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $reason = $row['cost_unpriced_reason'] === NULL ? 'none' : (string) $row['cost_unpriced_reason'];
      $count = (int) $row['attempts'];
      $rows[] = ['reason' => $reason, 'attempts' => $count,
        'oldest_started_at' => $this->optionalIso($row['oldest'], $filter)];
      if (in_array($reason, self::ACTIONABLE_REASONS, TRUE)) {
        $actionable += $count;
      }
      elseif (in_array($reason, self::BLOCKED_REASONS, TRUE)) {
        $blocked += $count;
      }
      elseif (in_array($reason, self::BY_DESIGN_REASONS, TRUE)) {
        $byDesign += $count;
      }
    }
    return [
      'by_reason' => $rows,
      'actionable_total' => $actionable,
      'blocked_by_usage_total' => $blocked,
      'by_design_total' => $byDesign,
    ];
  }

  /**
   * The deduplicated queue over the same window, split by primary issue.
   *
   * @return array{total:int,by_issue:array<string,int>,families_overlap:bool}
   */
  private function queue(string $siteId, array $filter, array $families, bool $withCosts, int $stuckCutoff): array {
    $case = $this->primaryIssueSql($withCosts, $stuckCutoff);
    $query = $this->siteReports->scoped($siteId, $filter);
    $query->where("$case IS NOT NULL");
    $query->addExpression('COUNT(*)', 'total');
    foreach (self::ISSUES as $issue) {
      $alias = 'q_' . $issue;
      $query->addExpression(sprintf("SUM(CASE WHEN %s = '%s' THEN 1 ELSE 0 END)", $case, $issue), $alias);
    }
    $row = $query->execute()->fetchAssoc() ?: [];

    $byIssue = [];
    foreach (self::ISSUES as $issue) {
      $byIssue[$issue] = (int) ($row['q_' . $issue] ?? 0);
    }
    $familySum = $families['incomplete']['unknown_attempt']['count']
      + $families['incomplete']['stuck_intent']['count']
      + $families['usage_unknown']['missing']['count']
      + $families['usage_unknown']['invalid']['count'];
    if ($withCosts) {
      $familySum += $families['unpriced']['actionable_total'];
    }
    return [
      'total' => (int) ($row['total'] ?? 0),
      'by_issue' => $byIssue,
      // Families count their own members, so overlapping attempts make the
      // family sum exceed the queue; the queue is the real work count.
      'families_overlap' => $familySum > (int) ($row['total'] ?? 0),
    ];
  }

  /**
   * Pipeline health: unprocessed and quarantined events, ages and reasons.
   *
   * Current state, not window-scoped; an old backlog shows however narrow the
   * report window is.
   *
   * @return array{pending_events:int,oldest_pending:string|null,oldest_pending_age_minutes:int|null,quarantined_events:int,oldest_quarantined:string|null,oldest_quarantined_age_minutes:int|null,quarantine_reasons:list<array>}
   */
  private function pipeline(string $siteId, int $nowMs): array {
    $utc = new \DateTimeZone('UTC');
    $pending = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNull('e.processed_at')
      ->isNull('e.quarantined_at')
      ->condition('e.site_id', $siteId);
    $pending->addExpression('COUNT(*)', 'count');
    $pending->addExpression('MIN(e.received_at)', 'oldest');
    $row = $pending->execute()->fetchAssoc() ?: [];

    $quarantined = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNotNull('e.quarantined_at')
      ->condition('e.site_id', $siteId);
    $quarantined->addExpression('COUNT(*)', 'count');
    $quarantined->addExpression('MIN(e.quarantined_at)', 'oldest');
    $qRow = $quarantined->execute()->fetchAssoc() ?: [];

    // addExpression() returns the alias string, not the query, so everything
    // after it is a separate statement.
    $reasonQuery = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNotNull('e.quarantined_at')
      ->condition('e.site_id', $siteId)
      ->fields('e', ['last_error']);
    $reasonQuery->addExpression('COUNT(*)', 'events');
    $reasonQuery->groupBy('e.last_error');
    $reasonQuery->orderBy('events', 'DESC');
    $reasonQuery->orderBy('e.last_error');
    $reasonQuery->range(0, self::REASON_LIMIT);
    $reasons = $reasonQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $oldestPending = $row['oldest'] ?? NULL;
    $oldestQuarantined = $qRow['oldest'] ?? NULL;
    return [
      'pending_events' => (int) ($row['count'] ?? 0),
      'oldest_pending' => $oldestPending === NULL ? NULL : ReportTime::iso((int) $oldestPending, $utc),
      'oldest_pending_age_minutes' => $oldestPending === NULL
        ? NULL : intdiv(max(0, $nowMs - (int) $oldestPending), self::MINUTE_MS),
      'quarantined_events' => (int) ($qRow['count'] ?? 0),
      'oldest_quarantined' => $oldestQuarantined === NULL ? NULL : ReportTime::iso((int) $oldestQuarantined, $utc),
      'oldest_quarantined_age_minutes' => $oldestQuarantined === NULL
        ? NULL : intdiv(max(0, $nowMs - (int) $oldestQuarantined), self::MINUTE_MS),
      'quarantine_reasons' => array_map(static fn(array $r): array => [
        'error' => $r['last_error'] === NULL ? 'none' : (string) $r['last_error'],
        'events' => (int) $r['events'],
      ], $reasons),
    ];
  }

  /**
   * Logical calls whose attempt numbers are not dense; events never arrived.
   *
   * Both producers derive attempt_no densely per (operation, logical call), so
   * a hole is the only Drupal-side signal of an event that did not arrive.
   * Candidates are found inside the window, then re-checked over the full
   * group without the time conditions: a window that cuts a logical call in
   * half must not read as a gap, and an attempt outside the window is not
   * missing.
   *
   * @return array{total:int,limit:int,candidates_truncated:bool,rows:list<array>}
   */
  private function attemptGaps(string $siteId, array $filter): array {
    $query = $this->siteReports->scoped($siteId, $filter);
    $query->fields('a', ['operation_id', 'logical_call_id', 'producer_id']);
    $query->addExpression('COUNT(*)', 'gap_count');
    $query->addExpression('MIN(a.attempt_no)', 'min_no');
    $query->addExpression('MAX(a.attempt_no)', 'max_no');
    $query->addExpression('MAX(a.started_at)', 'last_started_at');
    $query->groupBy('a.operation_id');
    $query->groupBy('a.logical_call_id');
    $query->groupBy('a.producer_id');
    // Full expressions, not aliases: PostgreSQL rejects aliases in HAVING.
    $query->having('COUNT(*) <> MAX(a.attempt_no) - MIN(a.attempt_no) + 1 OR MIN(a.attempt_no) > 1');
    $query->orderBy('last_started_at', 'DESC');
    // Every logical call straddling the window start is a candidate, so the
    // re-check below is bounded; the newest candidates are checked first.
    $query->range(0, self::MAX_GAP_CANDIDATES + 1);
    $candidates = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $candidatesTruncated = count($candidates) > self::MAX_GAP_CANDIDATES;
    if ($candidatesTruncated) {
      array_pop($candidates);
    }

    // Re-check every candidate over its full group; drop window artifacts.
    // The group is read without any report filter: a retry on another model or
    // channel is still part of the logical call and must not read as missing.
    $real = [];
    foreach ($candidates as $candidate) {
      $numbers = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
        ->fields('a', ['attempt_no'])
        ->condition('a.site_id', $siteId)
        ->condition('a.operation_id', $candidate['operation_id'])
        ->condition('a.logical_call_id', $candidate['logical_call_id'])
        ->execute()
        ->fetchCol();
      $present = array_flip(array_map('intval', $numbers));
      $max = (int) max(array_keys($present));
      $missing = [];
      for ($n = 1; $n <= $max && count($missing) < self::MAX_MISSING_NUMBERS; $n++) {
        if (!isset($present[$n])) {
          $missing[] = $n;
        }
      }
      if ($missing === []) {
        continue;
      }
      $candidate['missing'] = $missing;
      $real[] = $candidate;
    }
    usort($real, static function (array $a, array $b): int {
      return [(int) $b['last_started_at'], $a['operation_id'], $a['logical_call_id']]
        <=> [(int) $a['last_started_at'], $b['operation_id'], $b['logical_call_id']];
    });

    $rows = array_slice($real, 0, self::LIST_LIMIT);
    return [
      'total' => count($real),
      'limit' => self::LIST_LIMIT,
      // TRUE when more candidates existed than were re-checked; total is then
      // a lower bound.
      'candidates_truncated' => $candidatesTruncated,
      'rows' => array_map(fn(array $row): array => [
        'operation_id' => (string) $row['operation_id'],
        'logical_call_id' => (string) $row['logical_call_id'],
        'producer_id' => (string) $row['producer_id'],
        'missing_attempt_numbers' => array_map('intval', $row['missing']),
        'last_started_at' => $this->optionalIso($row['last_started_at'], $filter),
        'resolution' => 'investigate',
      ], $rows),
    ];
  }

  /**
   * Quarantined events of the site, newest first; the fix is manual release.
   *
   * @return array{total:int,limit:int,rows:list<array>}
   */
  private function quarantinedEvents(string $siteId): array {
    $utc = new \DateTimeZone('UTC');
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->isNotNull('e.quarantined_at')
      ->condition('e.site_id', $siteId)
      ->fields('e', ['id', 'event_id', 'attempt_id', 'event_type', 'operation_id',
        'failure_count', 'last_error', 'quarantined_at'])
      ->orderBy('e.quarantined_at', 'DESC')
      ->orderBy('e.id', 'DESC')
      ->range(0, self::LIST_LIMIT + 1);
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $total = $this->projection->quarantinedCount($siteId);
    $truncated = count($rows) > self::LIST_LIMIT;
    if ($truncated) {
      array_pop($rows);
    }
    return [
      'total' => $total,
      'limit' => self::LIST_LIMIT,
      'rows' => array_map(static fn(array $row): array => [
        'event_id' => (string) $row['event_id'],
        'attempt_id' => (string) $row['attempt_id'],
        'operation_id' => (string) $row['operation_id'],
        'event_type' => (string) $row['event_type'],
        'failure_count' => (int) $row['failure_count'],
        'last_error' => $row['last_error'] === NULL ? NULL : (string) $row['last_error'],
        'quarantined_at' => ReportTime::iso((int) $row['quarantined_at'], $utc),
        'resolution' => 'release_quarantine',
      ], $rows),
    ];
  }

  /**
   * The primary issue of one attempt row, in A > B > C priority order.
   *
   * Mirrors primaryIssueSql() branch for branch; the queue test asserts the
   * SQL predicate and this classification agree on an overlapping scenario.
   */
  private function classify(array $row, bool $withCosts, int $stuckCutoff): ?string {
    if ($row['state'] === 'unknown') {
      return 'unknown_attempt';
    }
    if (in_array($row['state'], ['prepared', 'in_flight'], TRUE) && (int) $row['started_at'] <= $stuckCutoff) {
      return 'stuck_intent';
    }
    if ($row['dispatch_state'] === 'sent') {
      if ($row['usage_quality'] === 'missing') {
        return 'usage_missing';
      }
      if ($row['usage_quality'] === 'invalid') {
        return 'usage_invalid';
      }
    }
    if ($withCosts && $row['cost_valuation_state'] === CostRatingService::STATE_UNPRICED
      && in_array((string) $row['cost_unpriced_reason'], self::ACTIONABLE_REASONS, TRUE)) {
      return 'unpriced';
    }
    return NULL;
  }

  /**
   * SQL CASE naming the primary issue of each attempt row.
   *
   * Self-contained literals only (the stuck cutoff is an inlined integer), so
   * the same expression can drive WHERE, aggregate splits and the count query.
   */
  private function primaryIssueSql(bool $withCosts, int $stuckCutoff): string {
    return "CASE"
      . " WHEN a.state = 'unknown' THEN 'unknown_attempt'"
      . sprintf(" WHEN a.state IN ('prepared', 'in_flight') AND a.started_at <= %d THEN 'stuck_intent'", $stuckCutoff)
      . " WHEN a.dispatch_state = 'sent' AND a.usage_quality = 'missing' THEN 'usage_missing'"
      . " WHEN a.dispatch_state = 'sent' AND a.usage_quality = 'invalid' THEN 'usage_invalid'"
      . ($withCosts
        ? sprintf(" WHEN a.cost_valuation_state = '%s' AND a.cost_unpriced_reason IN ('%s') THEN 'unpriced'",
          CostRatingService::STATE_UNPRICED, implode("', '", self::ACTIONABLE_REASONS))
        : '')
      . " END";
  }

  /**
   * Which entry resolves an issue; the write stays with that entry.
   */
  private function resolution(string $issue, string $producerId): string {
    return match ($issue) {
      'unknown_attempt', 'stuck_intent' => $producerId === LocalUsageProducer::PRODUCER_ID
        ? 'cms_reconcile' : 'node_reconcile',
      'usage_missing', 'usage_invalid' => 'investigate',
      'unpriced' => 'price_book',
      default => 'investigate',
    };
  }

  /**
   * The stuck threshold in minutes; default 60, accepted range 5..1440.
   */
  private function stuckMinutes(array $query): int {
    return self::intInRange($query, 'stuck_after_minutes', self::MIN_STUCK_MINUTES,
      self::MAX_STUCK_MINUTES, self::DEFAULT_STUCK_MINUTES, 'stuck_after_minutes');
  }

  /**
   * An integer query parameter, clamped to no range but rejected when unusable.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   */
  private static function intInRange(array $query, string $key, int $min, int $max,
    int $default, string $label): int {
    $value = $query[$key] ?? NULL;
    if ($value === NULL || (is_string($value) && trim($value) === '')) {
      return $default;
    }
    $text = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : NULL);
    if ($text === NULL || !preg_match('/^\d{1,7}$/', $text)) {
      throw new UsageReportException('invalid_request', "$label must be an integer between $min and $max");
    }
    $number = (int) $text;
    if ($number < $min || $number > $max) {
      throw new UsageReportException('invalid_request', "$label must be an integer between $min and $max");
    }
    return $number;
  }

  /**
   * Decodes the opaque pagination cursor "(started_at, attempt_id), latest first".
   *
   * @return array{0:int,1:string}|null
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   */
  private function parseCursor(array $query): ?array {
    $text = self::text($query, 'cursor');
    if ($text === NULL) {
      return NULL;
    }
    if (!preg_match('/^(\d{1,13}),([A-Za-z0-9_.:-]{1,128})$/', $text, $match)) {
      throw new UsageReportException('invalid_request',
        'cursor must be the next_cursor value of the previous page');
    }
    return [(int) $match[1], $match[2]];
  }

  /**
   * Pivots a (producer_id, model_id, unknown_count, stuck_count) grouping.
   *
   * @return array{0:list<array>,1:list<array>}
   *   [by producer, by model], each row {unknown_attempt, stuck_intent} counts.
   */
  private function pivotSplit(array $rows, string $unknownKey, string $stuckKey): array {
    $byProducer = [];
    $byModel = [];
    foreach ($rows as $row) {
      $byProducer[$row['producer_id']]['unknown_attempt'] = (int) $row[$unknownKey]
        + ($byProducer[$row['producer_id']]['unknown_attempt'] ?? 0);
      $byProducer[$row['producer_id']]['stuck_intent'] = (int) $row[$stuckKey]
        + ($byProducer[$row['producer_id']]['stuck_intent'] ?? 0);
      $byModel[$row['model_id']]['unknown_attempt'] = (int) $row[$unknownKey]
        + ($byModel[$row['model_id']]['unknown_attempt'] ?? 0);
      $byModel[$row['model_id']]['stuck_intent'] = (int) $row[$stuckKey]
        + ($byModel[$row['model_id']]['stuck_intent'] ?? 0);
    }
    $shape = static function (array $map, string $key): array {
      $rows = [];
      foreach ($map as $value => $counts) {
        $rows[] = [$key => (string) $value] + $counts;
      }
      usort($rows, static fn(array $a, array $b): int =>
        [($b['unknown_attempt'] + $b['stuck_intent']), $a[$key]]
        <=> [($a['unknown_attempt'] + $a['stuck_intent']), $b[$key]]);
      return $rows;
    };
    return [$shape($byProducer, 'producer'), $shape($byModel, 'model')];
  }

  /**
   * The report filter as displayed, plus the quality-specific threshold.
   */
  private function describeFilter(array $filter, array $query): array {
    return $this->siteReports->describeFilter($filter)
      + ['stuck_after_minutes' => $this->stuckMinutes($query)];
  }

  /**
   * The projection freshness envelope shared with the other admin reports.
   *
   * @return array{data_as_of:string|null,projection_version:int|null}
   */
  private function envelope(string $siteId): array {
    $watermark = $this->projection->watermark($siteId);
    return [
      'data_as_of' => $watermark === NULL ? NULL
        : ReportTime::iso($watermark['data_as_of'], new \DateTimeZone('UTC')),
      'projection_version' => $watermark['projection_version'] ?? NULL,
    ];
  }

  /**
   * A millisecond instant as ISO 8601 in the report timezone; NULL stays NULL.
   */
  private function optionalIso(mixed $value, array $filter): ?string {
    return $value === NULL ? NULL : ReportTime::iso((int) $value, $filter['timezone']);
  }

  private static function text(array $query, string $key): ?string {
    $value = $query[$key] ?? NULL;
    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

}
