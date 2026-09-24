<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;

/**
 * Read-only site-wide usage reports for administrators (UB3.3).
 *
 * Everything is scoped to the registered site and computed from the attempt
 * projection. The site is fixed by registration; a request cannot override it.
 * Filter keys accepted beyond the user report: `user` (actor_user_id),
 * `channel` (provider_account_ref), `payer`, `role` (billing_role).
 *
 * Quantity endpoints (summary/timeseries/breakdown) require `view site ai usage`.
 * Cost figures require the additional `view ai supplier costs` permission and
 * are on a separate endpoint; they are never folded into quantity responses.
 *
 * Time bucketing matches the user report: buckets are cut on wall-clock
 * boundaries of the requested IANA timezone. For site-wide reports we read
 * the attempt rows in the window directly and bucket in PHP, the same way
 * UsageReportService works; the 20,000-attempt scan cap applies here too.
 *
 * A dimension value the attempt lacks (no actor, no billing role) reads as
 * `none` in every response. Under one filter the role split, the buckets and
 * each breakdown add up to the summary; only `operations` may exceed it,
 * since one operation can span several models, features or roles.
 */
final class SiteUsageReportService {

  private const LABEL = '/^[A-Za-z0-9_.:-]{1,64}\z/';
  /** Reported value of a dimension column that is NULL on the attempt. */
  private const NONE = 'none';
  private const TOKENS = ['input_tokens_total', 'input_tokens_cache_read', 'input_tokens_cache_write',
    'output_tokens_total', 'output_tokens_reasoning'];
  /** Dimensions whose projection column is nullable; the others are NOT NULL. */
  private const NULLABLE_DIMENSIONS = ['users', 'roles'];

  /** Breakdown dimensions exposed on the admin endpoints. */
  public const DIMENSIONS = ['users', 'features', 'models', 'channels', 'roles'];
  /** Limit value asking breakdown()/costs() for every row; internal callers only. */
  public const UNLIMITED = -1;

  public function __construct(
    private readonly Connection $database,
    private readonly UsageProjectionService $projection,
    private readonly UsageReportService $userReports,
    private readonly PriceBookService $priceBook,
  ) {}

  /**
   * Normalizes the admin report filter, reusing the user-report filter plus admin keys.
   *
   * @param array $query
   *   Query parameters: `from`, `to`, `timezone`, `feature`, `model`, plus
   *   `user`, `channel`, `payer`, `role`.
   * @param int $nowMs
   *   Current request time in ms.
   * @param string $granularity
   *   `day` or `hour`; selects the maximum window.
   *
   * @return array{from:int,to:int,timezone:\DateTimeZone,feature:?string,model:?string,user:?string,channel:?string,payer:?string,role:?string}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   */
  public function parseFilter(array $query, int $nowMs, string $granularity = 'day'): array {
    $filter = $this->userReports->parseFilter($query, $nowMs, $granularity);
    foreach (['user', 'channel'] as $key) {
      $value = self::text($query, $key);
      if ($value !== NULL && !preg_match(self::LABEL, $value)) {
        throw new UsageReportException('invalid_request', "$key must be a label of at most 64 characters");
      }
      $filter[$key] = $value;
    }
    $payer = self::text($query, 'payer');
    if ($payer !== NULL && !in_array($payer, UsageReportService::PAYERS, TRUE)) {
      throw new UsageReportException('invalid_request', 'payer must be one of ' . implode(', ', UsageReportService::PAYERS));
    }
    $filter['payer'] = $payer;
    $role = self::text($query, 'role');
    if ($role !== NULL && !in_array($role, UsageReportService::ROLES, TRUE)) {
      throw new UsageReportException('invalid_request', 'role must be one of ' . implode(', ', UsageReportService::ROLES));
    }
    $filter['role'] = $role;
    return $filter;
  }

  /**
   * Site-wide summary: operations, active users, attempts, tokens, images, by-role split.
   *
   * Does not include cost figures; those are on the costs endpoint.
   */
  public function summary(string $siteId, array $filter): array {
    $rows = $this->fetchAttempts($siteId, $filter);
    return [
      'filter' => $this->describeFilter($filter),
      'totals' => $this->aggregateTotals($rows),
      'by_role' => $this->aggregateByRole($rows),
      'charges' => ['status' => 'not_enabled'],
    ] + $this->completeness($siteId);
  }

  /**
   * Time series over the window, bucketed by local wall-clock granularity.
   *
   * @return array{filter:array,buckets:list<array>}
   */
  public function timeseries(string $siteId, array $filter, string $granularity): array {
    if (!in_array($granularity, ReportTime::GRANULARITIES, TRUE)) {
      throw new UsageReportException('invalid_request', 'granularity must be hour or day');
    }
    $rows = $this->fetchAttempts($siteId, $filter);
    $tz = $filter['timezone'];
    $format = ReportTime::bucketFormat($granularity);
    $grouped = [];
    foreach ($rows as $row) {
      $grouped[ReportTime::at((int) $row['started_at'], $tz)->format($format)][] = $row;
    }
    $buckets = [];
    foreach (ReportTime::bucketLabels($filter['from'], $filter['to'], $tz, $granularity) as $label) {
      $bucketRows = $grouped[$label] ?? [];
      $buckets[] = ['bucket_start' => $label, 'empty' => $bucketRows === []] + $this->aggregateTotals($bucketRows);
    }
    return [
      'filter' => $this->describeFilter($filter) + ['granularity' => $granularity],
      'buckets' => $buckets,
    ] + $this->completeness($siteId);
  }

  /**
   * Breakdown of usage by a single dimension (top-N by attempts).
   *
   * @param string $siteId
   * @param array $filter
   * @param string $dimension
   *   One of self::DIMENSIONS.
   * @param int $limit
   *   Maximum rows returned (top-N by attempts desc). Default 50, max 200.
   *
   * @return array{filter:array,rows:list<array>,row_count:int,truncated:bool,operations_exclusive:bool,limit:int}
   */
  public function breakdown(string $siteId, array $filter, string $dimension, int $limit = 50): array {
    $dimExpr = $this->dimensionExpression($dimension);
    // UNLIMITED is for exports and the consistency check, which need every row.
    $unlimited = $limit === self::UNLIMITED;
    $limit = $unlimited ? self::UNLIMITED : max(1, min($limit, 200));

    $query = $this->scoped($siteId, $filter);
    $query->addExpression($dimExpr, 'dim');
    $query->addExpression('COUNT(*)', 'attempts');
    $query->addExpression('COUNT(DISTINCT a.operation_id)', 'operations');
    if ($dimension !== 'users') {
      $query->addExpression('COUNT(DISTINCT a.actor_user_id)', 'active_users');
    }
    $this->addCountExpressions($query);
    $this->addSumExpressions($query);
    $query->groupBy('dim');
    $query->orderBy('attempts', 'DESC');
    $query->orderBy('dim', 'ASC');
    if (!$unlimited) {
      // One row beyond the limit tells whether the list is truncated.
      $query->range(0, $limit + 1);
    }
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $truncated = !$unlimited && count($rows) > $limit;
    if ($truncated) {
      array_pop($rows);
    }

    $countQuery = $this->scoped($siteId, $filter);
    $countQuery->addExpression("COUNT(DISTINCT $dimExpr)", 'c');
    $rowCount = (int) $countQuery->execute()->fetchField();

    return [
      'filter' => $this->describeFilter($filter) + ['dimension' => $dimension],
      'rows' => array_map(fn(array $row): array => $this->shapeDimensionRow($row, $dimension), $rows),
      'row_count' => $rowCount,
      'truncated' => $truncated,
      'operations_exclusive' => $dimension === 'users',
      'limit' => $limit,
    ] + $this->completeness($siteId);
  }

  /**
   * Checks that every split of one window adds up to its summary (UB3.4).
   *
   * All figures are read under the same filter; the watermark is returned so
   * the caller can tell whether the projection moved between reads. Each
   * check names the split, the summary value and the sum over the split.
   * `operations` is compared only where one operation belongs to one row
   * (the users dimension); elsewhere it may legitimately exceed the summary.
   *
   * @return array{consistent:bool,checks:list<array{name:string,expected:string,actual:string,ok:bool}>,filter:array}
   */
  public function consistency(string $siteId, array $filter, bool $withCosts): array {
    $before = $this->projection->watermark($siteId);
    $summary = $this->summary($siteId, $filter);
    $totals = $summary['totals'];
    $checks = [];
    $compare = static function (string $name, string|int $expected, string|int $actual) use (&$checks): void {
      $checks[] = ['name' => $name, 'expected' => (string) $expected, 'actual' => (string) $actual,
        'ok' => (string) $expected === (string) $actual];
    };
    $sumOf = static function (array $rows, string $key): string {
      $sum = '0';
      foreach ($rows as $row) {
        $sum = self::addDecimal($sum, (string) $row[$key]);
      }
      return $sum;
    };
    $tokenSumOf = static function (array $rows, string $token): string {
      $sum = '0';
      foreach ($rows as $row) {
        $sum = self::addDecimal($sum, (string) $row['tokens'][$token]);
      }
      return $sum;
    };
    $splits = ['by_role' => array_values($summary['by_role'])];
    foreach (self::DIMENSIONS as $dimension) {
      $splits["breakdown:$dimension"] = $this->breakdown($siteId, $filter, $dimension, self::UNLIMITED)['rows'];
    }
    foreach ($splits as $name => $rows) {
      foreach (['attempts', 'sent', 'succeeded', 'failed', 'unknown', 'usage_missing'] as $key) {
        $compare("$name.$key", $totals[$key], $sumOf($rows, $key));
      }
      $compare("$name.input_tokens_total", $totals['tokens']['input_tokens_total'], $tokenSumOf($rows, 'input_tokens_total'));
      $compare("$name.output_tokens_total", $totals['tokens']['output_tokens_total'], $tokenSumOf($rows, 'output_tokens_total'));
      $compare("$name.images_generated", $totals['images_generated'], $sumOf($rows, 'images_generated'));
    }
    $users = $splits['breakdown:users'];
    $compare('breakdown:users.operations', $totals['operations'], $sumOf($users, 'operations'));
    $compare('breakdown:users.active_users', $totals['active_users'],
      count(array_filter($users, static fn(array $row): bool => $row['dimension'] !== self::NONE)));
    if ($withCosts) {
      $costs = $this->costs($siteId, $filter, 'models', self::UNLIMITED);
      $byCurrency = [];
      foreach ($costs['rows'] as $row) {
        foreach ($row['by_currency'] as $key => $cell) {
          $byCurrency[$key] ??= ['attempts' => '0', 'micros' => '0'];
          $byCurrency[$key]['attempts'] = self::addDecimal($byCurrency[$key]['attempts'], (string) $cell['attempts']);
          $byCurrency[$key]['micros'] = self::addDecimal($byCurrency[$key]['micros'], $cell['cost_micros']);
        }
      }
      foreach ($costs['totals'] as $total) {
        $key = $total['currency'] ?? self::NONE;
        $compare("costs:$key.attempts", $total['attempts'], $byCurrency[$key]['attempts'] ?? '0');
        $compare("costs:$key.micros", self::addDecimal($total['rated_micros'], $total['reconciled_micros']),
          $byCurrency[$key]['micros'] ?? '0');
      }
    }
    $after = $this->projection->watermark($siteId);
    return [
      'consistent' => !in_array(FALSE, array_column($checks, 'ok'), TRUE),
      'checks' => $checks,
      'filter' => $summary['filter'],
      'watermark_stable' => ($before['last_event_id'] ?? NULL) === ($after['last_event_id'] ?? NULL),
    ] + $this->completeness($siteId);
  }

  /**
   * The site's operations in the window, newest first (admin drilldown).
   *
   * Same head expressions, status filter and `(created_at, operation_id)`
   * cursor as the user list; the head query runs on the admin filter
   * (user/channel/payer/role included) and every row carries `actor_user_id`.
   *
   * @return array{filter:array,operations:list<array>,next_cursor:?string,limit:int}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   *   `invalid_request` for an unknown status or an unusable cursor.
   */
  public function operations(string $siteId, array $filter, ?string $cursor, int $limit,
    ?string $status = NULL): array {
    $limit = max(1, min($limit, UsageReportService::MAX_LIMIT));
    if ($status !== NULL && !in_array($status, UsageReportService::STATUSES, TRUE)) {
      throw new UsageReportException('invalid_request', 'status must be one of ' . implode(', ', UsageReportService::STATUSES));
    }
    $query = $this->scoped($siteId, $filter)
      ->fields('a', ['operation_id'])
      ->groupBy('a.operation_id')
      ->range(0, $limit + 1);
    $query->addExpression('MIN(a.started_at)', 'created_at');
    $query->addExpression(UsageReportService::countWhere("a.state IN ('prepared', 'in_flight')"), 'open_count');
    foreach (['succeeded', 'failed', 'unknown', 'not_sent'] as $state) {
      $query->addExpression(UsageReportService::countWhere("a.state = '$state'"), $state . '_count');
    }
    $query->orderBy('created_at', 'DESC')->orderBy('a.operation_id', 'DESC');
    if ($cursor !== NULL) {
      [$cursorTs, $cursorId] = UsageReportService::decodeCursor($cursor);
      $query->having('(MIN(a.started_at) < :cursor_ts OR (MIN(a.started_at) = :cursor_ts AND a.operation_id < :cursor_id))',
        [':cursor_ts' => $cursorTs, ':cursor_id' => $cursorId]);
    }
    if ($status !== NULL) {
      $query->having(UsageReportService::statusHaving($status));
    }
    $heads = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $next = NULL;
    if (count($heads) > $limit) {
      array_pop($heads);
      $last = end($heads);
      $next = UsageReportService::encodeCursor((int) $last['created_at'], (string) $last['operation_id']);
    }
    $ids = array_column($heads, 'operation_id');
    $byOperation = [];
    foreach ($this->attemptsOfOperations($siteId, $filter, $ids) as $row) {
      $byOperation[$row['operation_id']][] = $row;
    }
    $delivered = $this->userReports->deliveredByOperation($siteId, $ids);
    $operations = [];
    foreach ($heads as $head) {
      $id = (string) $head['operation_id'];
      $rows = $byOperation[$id] ?? [];
      $operations[] = $this->userReports->describeOperation($id, $rows, $delivered[$id] ?? 0, $filter['timezone'])
        + ['actor_user_id' => self::leadActor($rows)];
    }
    return [
      'filter' => $this->describeFilter($filter) + ['status' => $status],
      'operations' => $operations,
      'next_cursor' => $next,
      'limit' => $limit,
    ] + $this->completeness($siteId);
  }

  /**
   * One operation of the site with its attempts and deliveries, or NULL when unknown.
   *
   * Reads the whole operation, not just the window, like the user detail: an
   * administrator drills in from a row already inside the filter. The summary
   * and every attempt carry `actor_user_id`, which the user report omits.
   */
  public function operation(string $siteId, string $operationId, \DateTimeZone $tz): ?array {
    $rows = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a')
      ->condition('a.site_id', $siteId)
      ->condition('a.operation_id', $operationId)
      ->orderBy('a.started_at')->orderBy('a.id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
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
    return $this->userReports->describeOperation($operationId, $rows, $committed, $tz)
      + ['actor_user_id' => self::leadActor($rows)] + [
        'attempt_list' => array_map(fn(array $row): array =>
          $this->userReports->describeAttempt($row, $tz) + [
            'actor_user_id' => $row['actor_user_id'] === NULL ? NULL : (string) $row['actor_user_id'],
          ], $rows),
        'deliveries' => array_map(static fn(array $d): array => [
          'attempt_id' => $d['attempt_id'],
          'output_index' => (int) $d['output_index'],
          'artifact_kind' => $d['artifact_kind'],
          'artifact_ref' => $d['artifact_ref'],
          'state' => $d['state'],
          'delivered_at' => ReportTime::iso((int) $d['delivered_at'], $tz),
        ], $deliveries),
        // No sales price book or ledger exists yet (UB4); nothing was charged.
        'charges' => ['status' => 'not_enabled'],
      ] + $this->completeness($siteId);
  }

  /**
   * The full attempt rows of the given operations inside the window filter.
   *
   * @param list<string> $operationIds
   *
   * @return list<array<string,mixed>>
   */
  private function attemptsOfOperations(string $siteId, array $filter, array $operationIds): array {
    if ($operationIds === []) {
      return [];
    }
    $rows = [];
    foreach (array_chunk(array_values($operationIds), 500) as $chunk) {
      $rows = [...$rows, ...$this->scoped($siteId, $filter)
        ->fields('a')
        ->condition('a.operation_id', $chunk, 'IN')
        ->orderBy('a.started_at')->orderBy('a.id')
        ->execute()->fetchAll(\PDO::FETCH_ASSOC)];
    }
    return $rows;
  }

  /**
   * The actor of an operation's lead attempt: the primary call, else the first.
   */
  private static function leadActor(array $rows): ?string {
    foreach ($rows as $row) {
      if ($row['billing_role'] === 'primary') {
        return $row['actor_user_id'] === NULL ? NULL : (string) $row['actor_user_id'];
      }
    }
    $first = $rows[0] ?? NULL;
    return $first === NULL || $first['actor_user_id'] === NULL ? NULL : (string) $first['actor_user_id'];
  }

  /**
   * Every attempt row of the window, oldest first, for exports.
   *
   * Streams through the result instead of building the PHP array the reports
   * use, so an export may exceed the report scan cap up to its own row cap.
   *
   * @param string|null $actorUserId
   *   Restricts to one actor (the user export); NULL for the site.
   *
   * @return iterable<array<string,mixed>>
   */
  public function attemptRows(string $siteId, array $filter, ?string $actorUserId = NULL): iterable {
    $query = $this->scoped($siteId, $filter)->fields('a');
    if ($actorUserId !== NULL) {
      $query->condition('a.actor_user_id', $actorUserId);
    }
    $query->orderBy('a.started_at')->orderBy('a.id');
    foreach ($query->execute() as $row) {
      yield (array) $row;
    }
  }

  /** The `data_as_of` instant of the site projection in ms, or NULL before the first event. */
  public function watermark(string $siteId): ?int {
    return $this->projection->watermark($siteId)['data_as_of'] ?? NULL;
  }

  /**
   * Purchase cost of the window: per-currency totals, splits and top-N dimension rows.
   *
   * Requires both `view site ai usage` and `view ai supplier costs`. Amounts
   * are micros of the price book currency as decimal strings and are never
   * added across currencies; an attempt without a valuation is not counted.
   * Rows are ranked by attempts with the same rule as breakdown(). Cost is
   * not a sort key: micros of different currencies cannot be compared, and
   * unpriced attempts have none.
   *
   * @param string $siteId
   * @param array $filter
   * @param string $dimension
   *   One of self::DIMENSIONS.
   * @param int $limit
   *   Top-N rows per dimension. Default 50, max 200.
   *
   * @return array{filter:array,totals:list<array>,by_role:list<array>,by_state:list<array>,unpriced_reasons:list<array>,active_price_books:list<array>,rows:list<array>,row_count:int,truncated:bool,limit:int}
   */
  public function costs(string $siteId, array $filter, string $dimension, int $limit = 50): array {
    $dimExpr = $this->dimensionExpression($dimension);
    $limit = $limit === self::UNLIMITED ? self::UNLIMITED : max(1, min($limit, 200));

    // Totals by currency and valuation state.
    $totalQuery = $this->scopedCost($siteId, $filter);
    $totalQuery->fields('a', ['cost_currency', 'cost_valuation_state']);
    $totalQuery->addExpression('COUNT(*)', 'attempts');
    $totalQuery->addExpression('SUM(COALESCE(a.cost_total_micros, 0))', 'micros');
    $totalQuery->groupBy('a.cost_currency');
    $totalQuery->groupBy('a.cost_valuation_state');
    $totals = $this->shapeCurrencyTotals($totalQuery->execute()->fetchAll(\PDO::FETCH_ASSOC));

    // By billing role, per currency.
    $roleQuery = $this->scopedCost($siteId, $filter);
    $roleQuery->addExpression(sprintf("COALESCE(a.billing_role, '%s')", self::NONE), 'role');
    $roleQuery->fields('a', ['cost_currency']);
    $roleQuery->addExpression('COUNT(*)', 'attempts');
    $roleQuery->addExpression('SUM(COALESCE(a.cost_total_micros, 0))', 'micros');
    $roleQuery->groupBy('role');
    $roleQuery->groupBy('a.cost_currency');
    $roleQuery->orderBy('role');
    $byRole = $this->shapeByCurrencyGrouped($roleQuery->execute()->fetchAll(\PDO::FETCH_ASSOC), 'role');

    // By attempt state, per currency: failed calls cost money too.
    $stateQuery = $this->scopedCost($siteId, $filter);
    $stateQuery->fields('a', ['state', 'cost_currency']);
    $stateQuery->addExpression('COUNT(*)', 'attempts');
    $stateQuery->addExpression('SUM(COALESCE(a.cost_total_micros, 0))', 'micros');
    $stateQuery->groupBy('a.state');
    $stateQuery->groupBy('a.cost_currency');
    $stateQuery->orderBy('a.state');
    $byState = $this->shapeByCurrencyGrouped($stateQuery->execute()->fetchAll(\PDO::FETCH_ASSOC), 'state');

    // Why unpriced attempts have no figure, most frequent reason first.
    $reasonQuery = $this->scopedCost($siteId, $filter);
    $reasonQuery->condition('a.cost_valuation_state', CostRatingService::STATE_UNPRICED);
    $reasonQuery->fields('a', ['cost_unpriced_reason', 'cost_currency']);
    $reasonQuery->addExpression('COUNT(*)', 'attempts');
    $reasonQuery->groupBy('a.cost_unpriced_reason');
    $reasonQuery->groupBy('a.cost_currency');
    $reasonQuery->orderBy('attempts', 'DESC');
    $reasonQuery->orderBy('a.cost_unpriced_reason');
    $unpricedReasons = $this->shapeByCurrencyGrouped($reasonQuery->execute()->fetchAll(\PDO::FETCH_ASSOC),
      'cost_unpriced_reason', 'reason');

    // Top-N dimension values by attempts; one row beyond the limit detects truncation.
    $unlimited = $limit === self::UNLIMITED;
    $topQuery = $this->scopedCost($siteId, $filter);
    $topQuery->addExpression($dimExpr, 'dim');
    $topQuery->addExpression('COUNT(*)', 'attempts');
    $topQuery->groupBy('dim');
    $topQuery->orderBy('attempts', 'DESC');
    $topQuery->orderBy('dim', 'ASC');
    if (!$unlimited) {
      $topQuery->range(0, $limit + 1);
    }
    $topRows = $topQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $truncated = !$unlimited && count($topRows) > $limit;
    if ($truncated) {
      array_pop($topRows);
    }
    $topKeys = array_column($topRows, 'dim');

    $countQuery = $this->scopedCost($siteId, $filter);
    $countQuery->addExpression("COUNT(DISTINCT $dimExpr)", 'c');
    $rowCount = (int) $countQuery->execute()->fetchField();

    // Per-currency detail of the top keys.
    $byDim = [];
    if ($topKeys !== []) {
      $costQuery = $this->scopedCost($siteId, $filter);
      $costQuery->addExpression($dimExpr, 'dim');
      $costQuery->fields('a', ['cost_currency']);
      $costQuery->addExpression('COUNT(*)', 'attempts');
      $costQuery->addExpression('SUM(COALESCE(a.cost_total_micros, 0))', 'micros');
      $costQuery->addExpression(sprintf("SUM(CASE WHEN a.cost_valuation_state IN ('%s', '%s') THEN 1 ELSE 0 END)",
        CostRatingService::STATE_RATED_ESTIMATE, CostRatingService::STATE_RECONCILED), 'rated_count');
      $costQuery->addExpression(sprintf("SUM(CASE WHEN a.cost_valuation_state = '%s' THEN 1 ELSE 0 END)",
        CostRatingService::STATE_UNPRICED), 'unpriced_count');
      $costQuery->groupBy('dim');
      $costQuery->groupBy('a.cost_currency');
      // `dim` is a SELECT alias (a COALESCE for nullable columns), so the
      // restriction goes into HAVING; condition() would split it as table.column.
      $costQuery->having('dim IN (:keys[])', [':keys[]' => $topKeys]);
      foreach ($costQuery->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $dim = $row['dim'];
        $byDim[$dim] ??= ['dimension' => $dim, 'attempts' => 0, 'by_currency' => []];
        $currency = $row['cost_currency'] ?? self::NONE;
        $byDim[$dim]['attempts'] += (int) $row['attempts'];
        $byDim[$dim]['by_currency'][$currency] = [
          'currency' => $currency === self::NONE ? NULL : $currency,
          'attempts' => (int) $row['attempts'],
          'rated_count' => (int) $row['rated_count'],
          'unpriced_count' => (int) $row['unpriced_count'],
          'cost_micros' => (string) ($row['micros'] ?? 0),
        ];
      }
    }
    // Keep the top-N order; the detail query is not ordered.
    $rows = [];
    foreach ($topKeys as $key) {
      if (isset($byDim[$key])) {
        ksort($byDim[$key]['by_currency']);
        $rows[] = $byDim[$key];
      }
    }

    // Active price books (chat and image) at the end of the requested window.
    $activeBooks = [];
    foreach ([PriceBookService::KIND_SUPPLIER_CHAT, PriceBookService::KIND_SUPPLIER_IMAGE] as $kind) {
      $book = $this->priceBook->loadActive($siteId, $kind, $filter['to']);
      if ($book !== NULL) {
        $activeBooks[] = [
          'book_kind' => $kind,
          'version' => $book['version'],
          'currency' => $book['currency'],
          'rounding_policy' => $book['rounding_policy'],
          'effective_from' => ReportTime::iso((int) $book['effective_from'], new \DateTimeZone('UTC')),
          'effective_to' => $book['effective_to'] === NULL ? NULL : ReportTime::iso((int) $book['effective_to'], new \DateTimeZone('UTC')),
        ];
      }
    }

    return [
      'filter' => $this->describeFilter($filter) + ['dimension' => $dimension],
      'totals' => $totals,
      'by_role' => $byRole,
      'by_state' => $byState,
      'unpriced_reasons' => $unpricedReasons,
      'active_price_books' => $activeBooks,
      'rows' => $rows,
      'row_count' => $rowCount,
      'truncated' => $truncated,
      'limit' => $limit,
    ] + $this->completeness($siteId);
  }

  /**
   * The consumer watermark and pending/quarantined event counts.
   */
  private function completeness(string $siteId): array {
    $watermark = $this->projection->watermark($siteId);
    return [
      'data_as_of' => $watermark === NULL ? NULL : ReportTime::iso($watermark['data_as_of'], new \DateTimeZone('UTC')),
      'projection_version' => $watermark['projection_version'] ?? NULL,
      'pending_events' => $this->projection->pendingCount($siteId),
      'quarantined_events' => $this->projection->quarantinedCount($siteId),
    ];
  }

  public function describeFilter(array $filter): array {
    return [
      'from' => ReportTime::iso($filter['from'], $filter['timezone']),
      'to' => ReportTime::iso($filter['to'], $filter['timezone']),
      'timezone' => $filter['timezone']->getName(),
      'feature' => $filter['feature'],
      'model' => $filter['model'],
      'user' => $filter['user'] ?? NULL,
      'channel' => $filter['channel'] ?? NULL,
      'payer' => $filter['payer'] ?? NULL,
      'role' => $filter['role'] ?? NULL,
    ];
  }

  /**
   * The SQL expression of a dimension value; a NULL column reads as `none`.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   *   `invalid_request` for a dimension outside self::DIMENSIONS.
   */
  private function dimensionExpression(string $dimension): string {
    $column = match ($dimension) {
      'users' => 'actor_user_id',
      'features' => 'feature',
      'models' => 'model_id',
      'channels' => 'provider_account_ref',
      'roles' => 'billing_role',
      default => throw new UsageReportException('invalid_request',
        'dimension must be one of ' . implode(', ', self::DIMENSIONS)),
    };
    return in_array($dimension, self::NULLABLE_DIMENSIONS, TRUE)
      ? sprintf("COALESCE(a.%s, '%s')", $column, self::NONE)
      : "a.$column";
  }

  /**
   * SELECT base scoped to the site, time window and all filters.
   *
   * Shared with UsageQualityReportService, which runs over the same base
   * instead of copying the filter conditions. $withTime drops only the window
   * conditions; the attempt-gap recheck uses it so a window that cuts one
   * logical call in half cannot read as a missing attempt.
   *
   * @param bool $withTime
   *   FALSE omits the started_at window, keeping every other filter.
   */
  public function scoped(string $siteId, array $filter, bool $withTime = TRUE) {
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->condition('a.site_id', $siteId);
    if ($withTime) {
      $query->condition('a.started_at', $filter['from'], '>=')
        ->condition('a.started_at', $filter['to'], '<');
    }
    if ($filter['feature'] !== NULL) {
      $query->condition('a.feature', $filter['feature']);
    }
    if ($filter['model'] !== NULL) {
      $query->condition('a.model_id', $filter['model']);
    }
    if (($filter['user'] ?? NULL) !== NULL) {
      $query->condition('a.actor_user_id', $filter['user']);
    }
    if (($filter['channel'] ?? NULL) !== NULL) {
      $query->condition('a.provider_account_ref', $filter['channel']);
    }
    if (($filter['payer'] ?? NULL) !== NULL) {
      $query->condition('a.payer', $filter['payer']);
    }
    if (($filter['role'] ?? NULL) !== NULL) {
      $query->condition('a.billing_role', $filter['role']);
    }
    return $query;
  }

  /**
   * Scoped base restricted to attempts that have a cost valuation.
   */
  private function scopedCost(string $siteId, array $filter) {
    return $this->scoped($siteId, $filter)
      ->isNotNull('a.cost_valuation_state');
  }

  /**
   * Fetches all attempt rows in the window, enforcing the scan cap.
   *
   * @return list<array<string,mixed>>
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   *   `range_too_large` when the window covers more than MAX_ATTEMPTS.
   */
  private function fetchAttempts(string $siteId, array $filter): array {
    $count = (int) $this->scoped($siteId, $filter)->countQuery()->execute()->fetchField();
    if ($count > UsageReportService::MAX_ATTEMPTS) {
      throw new UsageReportException('range_too_large',
        sprintf('the window covers %d attempts; narrow it to at most %d',
          $count, UsageReportService::MAX_ATTEMPTS));
    }
    return $this->scoped($siteId, $filter)
      ->fields('a')
      ->orderBy('a.started_at')->orderBy('a.id')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function addCountExpressions($query): void {
    $query->addExpression("SUM(CASE WHEN a.dispatch_state = 'sent' THEN 1 ELSE 0 END)", 'sent');
    $query->addExpression("SUM(CASE WHEN a.state IN ('prepared', 'in_flight') THEN 1 ELSE 0 END)", 'open_count');
    foreach (['succeeded', 'failed', 'unknown', 'not_sent'] as $state) {
      $query->addExpression("SUM(CASE WHEN a.state = '$state' THEN 1 ELSE 0 END)", $state . '_count');
    }
    $finished = "a.state IN ('succeeded', 'failed', 'unknown')";
    $query->addExpression("SUM(CASE WHEN $finished AND a.usage_quality = 'reported' THEN 1 ELSE 0 END)", 'usage_reported_count');
    $query->addExpression("SUM(CASE WHEN $finished AND (a.usage_quality IS NULL OR a.usage_quality <> 'reported') THEN 1 ELSE 0 END)", 'usage_missing_count');
  }

  private function addSumExpressions($query): void {
    foreach ([...self::TOKENS, 'images_generated'] as $col) {
      $query->addExpression("SUM(CASE WHEN a.usage_quality = 'reported' THEN COALESCE(a.$col, 0) ELSE 0 END)", $col);
    }
  }

  /**
   * Aggregates attempt rows into the standard totals shape.
   *
   * Mirrors addCountExpressions()/addSumExpressions(): the same rules decide
   * the summary and buckets here and the breakdown rows in SQL.
   */
  private function aggregateTotals(array $rows): array {
    $counts = ['attempts' => 0, 'sent' => 0, 'open' => 0, 'succeeded' => 0, 'failed' => 0,
      'unknown' => 0, 'not_sent' => 0, 'usage_reported' => 0, 'usage_missing' => 0];
    $operations = [];
    $activeUsers = [];
    $sums = array_fill_keys([...self::TOKENS, 'images_generated'], 0);
    foreach ($rows as $row) {
      $counts['attempts']++;
      $operations[$row['operation_id']] = TRUE;
      if ($row['actor_user_id'] !== NULL) {
        $activeUsers[$row['actor_user_id']] = TRUE;
      }
      if ($row['dispatch_state'] === 'sent') {
        $counts['sent']++;
      }
      $state = (string) $row['state'];
      if (in_array($state, ['prepared', 'in_flight'], TRUE)) {
        $counts['open']++;
      }
      elseif (isset($counts[$state])) {
        $counts[$state]++;
      }
      $reported = $row['usage_quality'] === 'reported';
      if (in_array($state, ['succeeded', 'failed', 'unknown'], TRUE)) {
        $counts[$reported ? 'usage_reported' : 'usage_missing']++;
      }
      if ($reported) {
        foreach (array_keys($sums) as $column) {
          $sums[$column] += (int) ($row[$column] ?? 0);
        }
      }
    }
    return $counts + [
      'operations' => count($operations),
      'active_users' => count($activeUsers),
      'tokens' => array_map('strval', array_intersect_key($sums, array_flip(self::TOKENS))),
      'images_generated' => (string) $sums['images_generated'],
    ];
  }

  /**
   * Aggregates attempt rows by billing_role, `none` for attempts without one.
   *
   * @return array<string,array>
   */
  private function aggregateByRole(array $rows): array {
    $byRole = [];
    foreach ($rows as $row) {
      $byRole[$row['billing_role'] ?? self::NONE][] = $row;
    }
    ksort($byRole);
    return array_map(fn(array $roleRows): array => $this->aggregateTotals($roleRows), $byRole);
  }

  private function shapeDimensionRow(array $row, string $dimension): array {
    $base = [
      'dimension' => $row['dim'],
      'operations' => (int) $row['operations'],
      'attempts' => (int) $row['attempts'],
    ];
    if ($dimension !== 'users') {
      $base['active_users'] = (int) $row['active_users'];
    }
    return $base + $this->shapeTotalsFromDbRow($row);
  }

  private function shapeTotalsFromDbRow(array $row): array {
    $tokens = [];
    foreach (self::TOKENS as $t) {
      $tokens[$t] = (string) ($row[$t] ?? 0);
    }
    return [
      'sent' => (int) ($row['sent'] ?? 0),
      'open' => (int) ($row['open_count'] ?? 0),
      'succeeded' => (int) ($row['succeeded_count'] ?? 0),
      'failed' => (int) ($row['failed_count'] ?? 0),
      'unknown' => (int) ($row['unknown_count'] ?? 0),
      'not_sent' => (int) ($row['not_sent_count'] ?? 0),
      'usage_reported' => (int) ($row['usage_reported_count'] ?? 0),
      'usage_missing' => (int) ($row['usage_missing_count'] ?? 0),
      'tokens' => $tokens,
      'images_generated' => (string) ($row['images_generated'] ?? 0),
    ];
  }

  /**
   * Pivots rows with (group_key, currency, attempts, micros) into per-key per-currency.
   *
   * @param list<array> $rows
   * @param string $groupKey
   *   Column name for the grouping dimension; a NULL value reads as `none`.
   * @param string|null $outKey
   *   Output field name for the dimension value; defaults to $groupKey.
   *
   * @return list<array>
   */
  private function shapeByCurrencyGrouped(array $rows, string $groupKey, ?string $outKey = NULL): array {
    $outKey ??= $groupKey;
    $byKey = [];
    foreach ($rows as $row) {
      $key = $row[$groupKey] ?? self::NONE;
      $currency = $row['cost_currency'] ?? self::NONE;
      $byKey[$key] ??= [$outKey => $key, 'by_currency' => []];
      $byKey[$key]['by_currency'][$currency] = [
        'currency' => $currency === self::NONE ? NULL : $currency,
        'attempts' => (int) $row['attempts'],
        'cost_micros' => (string) ($row['micros'] ?? 0),
      ];
    }
    foreach ($byKey as &$entry) {
      ksort($entry['by_currency']);
    }
    unset($entry);
    return array_values($byKey);
  }

  /**
   * Pivots per-(currency, valuation_state) rows into per-currency totals.
   *
   * @return list<array{currency:?string,attempts:int,rated_estimate_attempts:int,reconciled_attempts:int,unpriced_attempts:int,rated_micros:string,reconciled_micros:string,estimated:bool}>
   */
  private function shapeCurrencyTotals(array $rows): array {
    $byCurrency = [];
    foreach ($rows as $row) {
      $currency = $row['cost_currency'] ?? self::NONE;
      $byCurrency[$currency] ??= [
        'currency' => $currency === self::NONE ? NULL : $currency,
        'attempts' => 0,
        'rated_estimate_attempts' => 0,
        'reconciled_attempts' => 0,
        'unpriced_attempts' => 0,
        'rated_micros' => '0',
        'reconciled_micros' => '0',
        'estimated' => TRUE,
      ];
      $attempts = (int) $row['attempts'];
      $micros = (int) ($row['micros'] ?? 0);
      $byCurrency[$currency]['attempts'] += $attempts;
      switch ($row['cost_valuation_state']) {
        case CostRatingService::STATE_RATED_ESTIMATE:
          $byCurrency[$currency]['rated_estimate_attempts'] += $attempts;
          $byCurrency[$currency]['rated_micros'] = (string) ((int) $byCurrency[$currency]['rated_micros'] + $micros);
          break;

        case CostRatingService::STATE_RECONCILED:
          $byCurrency[$currency]['reconciled_attempts'] += $attempts;
          $byCurrency[$currency]['reconciled_micros'] = (string) ((int) $byCurrency[$currency]['reconciled_micros'] + $micros);
          break;

        case CostRatingService::STATE_UNPRICED:
          $byCurrency[$currency]['unpriced_attempts'] += $attempts;
          break;
      }
    }
    ksort($byCurrency);
    return array_values($byCurrency);
  }

  /**
   * Exact addition of two non-negative decimal strings without bcmath.
   */
  private static function addDecimal(string $a, string $b): string {
    $a = ltrim($a, '0') ?: '0';
    $b = ltrim($b, '0') ?: '0';
    if (strlen($a) < 18 && strlen($b) < 18) {
      return (string) ((int) $a + (int) $b);
    }
    $out = '';
    $carry = 0;
    for ($i = strlen($a) - 1, $j = strlen($b) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
      $sum = $carry + ($i >= 0 ? (int) $a[$i] : 0) + ($j >= 0 ? (int) $b[$j] : 0);
      $out = ($sum % 10) . $out;
      $carry = intdiv($sum, 10);
    }
    return $out;
  }

  private static function text(array $query, string $key): ?string {
    $value = $query[$key] ?? NULL;
    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

}
