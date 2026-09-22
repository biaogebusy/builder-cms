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
    $limit = max(1, min($limit, 200));

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
    // One row beyond the limit tells whether the list is truncated.
    $query->range(0, $limit + 1);
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $truncated = count($rows) > $limit;
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
    $limit = max(1, min($limit, 200));

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
    $topQuery = $this->scopedCost($siteId, $filter);
    $topQuery->addExpression($dimExpr, 'dim');
    $topQuery->addExpression('COUNT(*)', 'attempts');
    $topQuery->groupBy('dim');
    $topQuery->orderBy('attempts', 'DESC');
    $topQuery->orderBy('dim', 'ASC');
    $topQuery->range(0, $limit + 1);
    $topRows = $topQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $truncated = count($topRows) > $limit;
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

  private function describeFilter(array $filter): array {
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
   */
  private function scoped(string $siteId, array $filter) {
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->condition('a.site_id', $siteId)
      ->condition('a.started_at', $filter['from'], '>=')
      ->condition('a.started_at', $filter['to'], '<');
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

  private static function text(array $query, string $key): ?string {
    $value = $query[$key] ?? NULL;
    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

}
