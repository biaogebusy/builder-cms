<?php

use Drupal\Core\Database\Database;
use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();
$plan = Runtime::plan();
$expected = Runtime::read('full-snapshot-plan-report.json');
$source = Database::getConnection('default', Runtime::sourceKey());
$target = Database::getConnection();
$apply = static function ($query, array $conditions) use (&$apply): void {
  foreach ($conditions as $condition) {
    if (isset($condition['conjunction'])) {
      $group = match ($condition['conjunction']) {
        'AND' => $query->andConditionGroup(),
        'OR' => $query->orConditionGroup(),
        default => throw new RuntimeException('Unsupported snapshot condition.'),
      };
      $apply($group, $condition['conditions']);
      $query->condition($group);
    }
    elseif ($condition['operator'] === 'IN' && !$condition['value']) {
      $query->where('1 = 0');
    }
    else {
      $query->condition($condition['column'], $condition['value'], $condition['operator']);
    }
  }
};
$report = ['status' => 'checking', 'execution_environment' => Runtime::environment(), 'tables' => 0, 'source_rows' => 0, 'schema_errors' => [], 'source_count_errors' => [], 'target_collision_tables' => [], 'business_rows_written' => 0];
$owned = Runtime::ownedRows();
if ($owned && (int) $target->select('xinshi_migrate_rows', 'r')->condition('dataset', $plan['source_sha256'], '<>')->countQuery()->execute()->fetchField()) {
  Runtime::reject('target_contains_a_different_snapshot_batch');
}
foreach ($plan['tables'] as $table => $specification) {
  if (!$source->schema()->tableExists($table) || !$target->schema()->tableExists($table)) {
    $report['schema_errors'][] = $table;
    continue;
  }
  foreach ($specification['columns'] as $column) {
    if (!$source->schema()->fieldExists($table, $column) || !$target->schema()->fieldExists($table, $column)) {
      $report['schema_errors'][] = $table . '.' . $column;
    }
  }
  $query = $source->select($table, 's');
  $apply($query, $specification['conditions'] ?? []);
  $count = (int) $query->countQuery()->execute()->fetchField();
  $report['source_rows'] += $count;
  $report['tables']++;
  if ($count !== $expected['table_counts'][$table]) {
    $report['source_count_errors'][] = $table;
  }
  if (!$owned) {
    $query = $target->select($table, 't');
    if ($table === 'key_value') {
      $query->condition('collection', ['pathauto_state.user', 'pathauto_state.node', 'pathauto_state.media', 'pathauto_state.taxonomy_term'], 'IN');
    }
    if ((int) $query->countQuery()->execute()->fetchField() !== (int) ($expected['preserved_target_rows'][$table] ?? 0)) {
      $report['target_collision_tables'][] = $table;
    }
  }
}
$report['status'] = $report['schema_errors'] || $report['source_count_errors'] || $report['target_collision_tables'] ? 'needs_attention' : 'ready';
$report['pending_source_exceptions'] = Runtime::pendingExceptions();
$report['plan_sha256'] = hash_file('sha256', Runtime::planPath());
$report['checked_at'] = gmdate(DATE_ATOM);
Runtime::write('runtime-preflight-report.json', $report);
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
if ($report['status'] !== 'ready') {
  Runtime::reject('source_or_target_preflight_did_not_pass');
}
