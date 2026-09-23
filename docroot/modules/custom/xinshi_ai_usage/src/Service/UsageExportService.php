<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;

/**
 * CSV exports of the usage reports (UB3.4).
 *
 * An export is a job row plus a file under the private file path. The report
 * scan cap already bounds the work, so a job runs inside the request that
 * creates it; the API still answers with a job the client polls and downloads,
 * so moving the run to a queue later changes nothing for callers. Files live
 * for LIFETIME_MS and are purged by cron; a download after that is 410.
 *
 * Rows come from the same services the pages read, so under one filter and
 * watermark a CSV agrees with the screen. The job stores `data_as_of` and the
 * filter it ran with for that comparison.
 */
final class UsageExportService {

  public const TABLE = 'ai_usage_export';
  public const LIFETIME_MS = 3_600_000;
  public const SETTING_PRIVATE_PATH = 'file_private_path';
  public const DIRECTORY = 'ai-usage-exports';
  /** Rows one export may write; wider windows must be split. */
  public const MAX_ROWS = 100_000;
  public const SCOPE_USER = 'user';
  public const SCOPE_SITE = 'site';
  public const KINDS = [
    self::SCOPE_USER => ['operations', 'attempts'],
    self::SCOPE_SITE => ['attempts', 'breakdown'],
  ];
  private const PAGE = 100;

  public function __construct(
    private readonly Connection $database,
    private readonly Settings $settings,
    private readonly UsageReportService $userReports,
    private readonly SiteUsageReportService $siteReports,
  ) {}

  /**
   * Runs an export and records it.
   *
   * @param array $filter
   *   A parsed report filter of the scope.
   * @param array $options
   *   `dimension` for breakdown exports, `include_costs` for site attempts.
   *
   * @return array
   *   The job as returned to the client.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\UsageReportException
   */
  public function create(string $siteId, string $scope, string $requester, string $kind, array $filter,
    array $options, int $nowMs): array {
    if (!in_array($kind, self::KINDS[$scope] ?? [], TRUE)) {
      throw new UsageReportException('invalid_request', 'kind must be one of ' . implode(', ', self::KINDS[$scope] ?? []));
    }
    $directory = $this->directory($siteId);
    $name = sprintf('%s-%s-%s-%s.csv', $scope, $kind, gmdate('Ymd\THis', intdiv($nowMs, 1000)), bin2hex(random_bytes(6)));
    $path = $directory . '/' . $name;
    $handle = fopen($path, 'wb');
    if ($handle === FALSE) {
      throw new UsageReportException('export_unavailable', 'the export file could not be created');
    }
    try {
      fwrite($handle, CsvWriter::BOM);
      [$rows, $dataAsOf] = match ($kind) {
        'operations' => $this->writeOperations($handle, $siteId, $requester, $filter),
        'attempts' => $this->writeAttempts($handle, $siteId, $scope === self::SCOPE_USER ? $requester : NULL,
          $filter, (bool) ($options['include_costs'] ?? FALSE)),
        'breakdown' => $this->writeBreakdown($handle, $siteId, $filter, (string) ($options['dimension'] ?? '')),
      };
    }
    catch (\Throwable $e) {
      fclose($handle);
      @unlink($path);
      throw $e;
    }
    fclose($handle);
    $id = (int) $this->database->insert(self::TABLE)->fields([
      'site_id' => $siteId,
      'scope' => $scope,
      'requester' => $requester,
      'kind' => $kind,
      'filter_json' => json_encode($this->describeFilter($filter, $options), JSON_UNESCAPED_SLASHES),
      'file_name' => $name,
      'row_count' => $rows,
      'byte_size' => filesize($path) ?: 0,
      'data_as_of' => $dataAsOf,
      'created_at' => $nowMs,
      'expires_at' => $nowMs + self::LIFETIME_MS,
    ])->execute();
    return $this->describe($this->row($id), $nowMs);
  }

  /**
   * The job with `$id` when it belongs to `$requester` in `$scope`, else NULL.
   */
  public function load(int $id, string $scope, string $requester, int $nowMs): ?array {
    $row = $this->row($id);
    if ($row === NULL || $row['scope'] !== $scope || $row['requester'] !== $requester) {
      return NULL;
    }
    return $this->describe($row, $nowMs);
  }

  /**
   * Absolute path of a job's file, or NULL once it expired or was purged.
   */
  public function filePath(array $job): ?string {
    if ($job['status'] !== 'ready') {
      return NULL;
    }
    $path = $this->directory($job['site_id'], FALSE) . '/' . $job['file_name'];
    return is_file($path) ? $path : NULL;
  }

  /**
   * Deletes expired files and rows; returns how many jobs were purged.
   */
  public function purgeExpired(int $nowMs): int {
    $rows = $this->database->select(self::TABLE, 'e')
      ->fields('e', ['id', 'site_id', 'file_name'])
      ->condition('e.expires_at', $nowMs, '<=')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $base = $this->settings->get(self::SETTING_PRIVATE_PATH);
    foreach ($rows as $row) {
      if (is_string($base) && $base !== '') {
        @unlink($base . '/' . self::DIRECTORY . '/' . $row['site_id'] . '/' . $row['file_name']);
      }
      $this->database->delete(self::TABLE)->condition('id', (int) $row['id'])->execute();
    }
    return count($rows);
  }

  /** The download file name offered to the browser. */
  public static function downloadName(array $job): string {
    return sprintf('ai-usage-%s-%s-%d.csv', $job['scope'], $job['kind'], $job['id']);
  }

  /**
   * @return array{0:int,1:?int}
   *   Rows written and the watermark the rows were read under.
   */
  private function writeOperations($handle, string $siteId, string $actor, array $filter): array {
    fwrite($handle, CsvWriter::row(['operation_id', 'created_at', 'updated_at', 'feature', 'stage', 'status',
      'payer', 'producer_id', 'models', 'attempts', 'sent', 'succeeded', 'failed', 'unknown', 'not_sent',
      'usage_reported', 'usage_missing', 'input_tokens_total', 'input_tokens_cache_read',
      'input_tokens_cache_write', 'output_tokens_total', 'output_tokens_reasoning', 'images_generated',
      'images_delivered']));
    $rows = 0;
    $cursor = NULL;
    $dataAsOf = NULL;
    do {
      $page = $this->userReports->operations($siteId, $actor, $filter, $cursor, self::PAGE);
      $dataAsOf ??= $page['data_as_of'];
      foreach ($page['operations'] as $op) {
        $t = $op['tokens'];
        fwrite($handle, CsvWriter::row([$op['operation_id'], $op['created_at'], $op['updated_at'], $op['feature'],
          $op['stage'], $op['status'], $op['payer'], $op['producer_id'], implode(' ', $op['models']),
          $op['attempts'], $op['sent'], $op['succeeded'], $op['failed'], $op['unknown'], $op['not_sent'],
          $op['usage_reported'], $op['usage_missing'], $t['input_tokens_total'], $t['input_tokens_cache_read'],
          $t['input_tokens_cache_write'], $t['output_tokens_total'], $t['output_tokens_reasoning'],
          $op['images_generated'], $op['images_delivered']]));
        $this->guard(++$rows);
      }
      $cursor = $page['next_cursor'];
    } while ($cursor !== NULL);
    return [$rows, $dataAsOf === NULL ? NULL : $this->instant($dataAsOf)];
  }

  private function writeAttempts($handle, string $siteId, ?string $actor, array $filter, bool $costs): array {
    $header = ['attempt_id', 'operation_id', 'logical_call_id', 'attempt_no', 'actor_user_id', 'feature',
      'stage', 'billing_role', 'payer', 'producer_id', 'provider_account_ref', 'requested_model', 'model',
      'state', 'dispatch_state', 'error_code', 'usage_quality', 'input_tokens_total', 'input_tokens_cache_read',
      'input_tokens_cache_write', 'output_tokens_total', 'output_tokens_reasoning', 'images_generated',
      'started_at', 'finished_at'];
    if ($costs) {
      $header = [...$header, 'cost_valuation_state', 'cost_currency', 'cost_total_micros', 'cost_unpriced_reason'];
    }
    fwrite($handle, CsvWriter::row($header));
    $rows = 0;
    $tz = $filter['timezone'];
    foreach ($this->siteReports->attemptRows($siteId, $filter, $actor) as $a) {
      $reported = $a['usage_quality'] === 'reported';
      $cells = [$a['attempt_id'], $a['operation_id'], $a['logical_call_id'], (int) $a['attempt_no'],
        $a['actor_user_id'], $a['feature'], $a['stage'], $a['billing_role'], $a['payer'], $a['producer_id'],
        $a['provider_account_ref'], $a['requested_model'], $a['model_id'], $a['state'], $a['dispatch_state'],
        $a['error_code'], $a['usage_quality'],
        $reported ? $a['input_tokens_total'] : NULL, $reported ? $a['input_tokens_cache_read'] : NULL,
        $reported ? $a['input_tokens_cache_write'] : NULL, $reported ? $a['output_tokens_total'] : NULL,
        $reported ? $a['output_tokens_reasoning'] : NULL, $reported ? $a['images_generated'] : NULL,
        ReportTime::iso((int) $a['started_at'], $tz),
        $a['finished_at'] === NULL ? NULL : ReportTime::iso((int) $a['finished_at'], $tz)];
      if ($costs) {
        $cells = [...$cells, $a['cost_valuation_state'], $a['cost_currency'], $a['cost_total_micros'],
          $a['cost_unpriced_reason']];
      }
      fwrite($handle, CsvWriter::row($cells));
      $this->guard(++$rows);
    }
    return [$rows, $this->siteReports->watermark($siteId)];
  }

  private function writeBreakdown($handle, string $siteId, array $filter, string $dimension): array {
    $report = $this->siteReports->breakdown($siteId, $filter, $dimension, SiteUsageReportService::UNLIMITED);
    fwrite($handle, CsvWriter::row([$dimension, 'operations', 'attempts', 'active_users', 'sent', 'open',
      'succeeded', 'failed', 'unknown', 'not_sent', 'usage_reported', 'usage_missing', 'input_tokens_total',
      'input_tokens_cache_read', 'input_tokens_cache_write', 'output_tokens_total', 'output_tokens_reasoning',
      'images_generated']));
    $rows = 0;
    foreach ($report['rows'] as $r) {
      $t = $r['tokens'];
      fwrite($handle, CsvWriter::row([$r['dimension'], $r['operations'], $r['attempts'], $r['active_users'] ?? NULL,
        $r['sent'], $r['open'], $r['succeeded'], $r['failed'], $r['unknown'], $r['not_sent'], $r['usage_reported'],
        $r['usage_missing'], $t['input_tokens_total'], $t['input_tokens_cache_read'], $t['input_tokens_cache_write'],
        $t['output_tokens_total'], $t['output_tokens_reasoning'], $r['images_generated']]));
      $rows++;
    }
    return [$rows, $report['data_as_of'] === NULL ? NULL : $this->instant($report['data_as_of'])];
  }

  private function guard(int $rows): void {
    if ($rows > self::MAX_ROWS) {
      throw new UsageReportException('range_too_large',
        sprintf('the export exceeds %d rows; narrow the window', self::MAX_ROWS));
    }
  }

  private function instant(string $iso): int {
    return (int) (new \DateTimeImmutable($iso))->format('Uv');
  }

  private function describeFilter(array $filter, array $options): array {
    $out = [
      'from' => ReportTime::iso($filter['from'], $filter['timezone']),
      'to' => ReportTime::iso($filter['to'], $filter['timezone']),
      'timezone' => $filter['timezone']->getName(),
    ];
    foreach (['feature', 'model', 'user', 'channel', 'payer', 'role'] as $key) {
      if (($filter[$key] ?? NULL) !== NULL) {
        $out[$key] = $filter[$key];
      }
    }
    if (($options['dimension'] ?? '') !== '') {
      $out['dimension'] = $options['dimension'];
    }
    if (!empty($options['include_costs'])) {
      $out['include_costs'] = TRUE;
    }
    return $out;
  }

  private function directory(string $siteId, bool $create = TRUE): string {
    $base = $this->settings->get(self::SETTING_PRIVATE_PATH);
    if (!is_string($base) || $base === '') {
      throw new UsageReportException('export_unavailable', 'file_private_path is not configured');
    }
    $directory = $base . '/' . self::DIRECTORY . '/' . $siteId;
    if ($create && !is_dir($directory) && !mkdir($directory, 0770, TRUE) && !is_dir($directory)) {
      throw new UsageReportException('export_unavailable', 'the export directory could not be created');
    }
    return $directory;
  }

  private function row(int $id): ?array {
    $row = $this->database->select(self::TABLE, 'e')->fields('e')->condition('e.id', $id)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  private function describe(array $row, int $nowMs): array {
    $expired = (int) $row['expires_at'] <= $nowMs;
    $utc = new \DateTimeZone('UTC');
    return [
      'id' => (int) $row['id'],
      'site_id' => $row['site_id'],
      'scope' => $row['scope'],
      'kind' => $row['kind'],
      'status' => $expired ? 'expired' : 'ready',
      'filter' => json_decode((string) $row['filter_json'], TRUE) ?: [],
      'row_count' => (int) $row['row_count'],
      'byte_size' => (int) $row['byte_size'],
      'data_as_of' => $row['data_as_of'] === NULL ? NULL : ReportTime::iso((int) $row['data_as_of'], $utc),
      'created_at' => ReportTime::iso((int) $row['created_at'], $utc),
      'expires_at' => ReportTime::iso((int) $row['expires_at'], $utc),
      'file_name' => $row['file_name'],
    ];
  }

}
