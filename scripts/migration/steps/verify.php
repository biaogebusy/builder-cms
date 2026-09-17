<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Read-only, full-row verification of the completed isolated snapshot. */
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\xinshi_migrate\SnapshotPlan;

umask(0077);
$started = microtime(TRUE);
$read = static fn(string $name): array => json_decode(file_get_contents(Runtime::path('') . $name), TRUE, 512, JSON_THROW_ON_ERROR);
$data = Runtime::plan();
$preflight = $read('full-snapshot-plan-report.json');
$import = $read('full-snapshot-import-report.json');
$baseline = $read('target-admin-before-full.private.json');
$target = Database::getConnection();
$source = Database::getConnection('default', Runtime::sourceKey());
if (($target->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()
  || $import['status'] !== 'imported'
  || hash_file('sha256', Runtime::path('reference-selected-ids.json')) !== $data['selection_sha256']
  || hash_file('sha256', Runtime::path('model-config-plan.private.json')) !== $data['configuration_sha256']) {
  throw new RuntimeException('The complete prepared import and its exact input plans are required.');
}
new Settings(array_replace(Settings::getAll(), ['xinshi_migration_plan_path' => Runtime::planPath()]));
\Drupal::getContainer()->set('xinshi_migrate.plan', new SnapshotPlan($target, \Drupal::configFactory()));
$manager = \Drupal::service('plugin.manager.migration');
$manager->clearCachedDefinitions();
$errors = [];
$checks = [];
$report = ['status' => 'checking', 'source_sha256' => $data['source_sha256'], 'tables' => [], 'checks' => [], 'error_count' => 0, 'source_written' => FALSE, 'execution_environment' => Runtime::environment()];
$fail = static function (string $kind, string $table, array $key = [], array $details = []) use (&$errors): void {
  $errors[] = compact('kind', 'table', 'key', 'details');
};
$check = static function (string $name, bool $passed, array $details = []) use (&$checks, $fail): void {
  $checks[$name] = ['passed' => $passed] + $details;
  if (!$passed) {
    $fail($name, 'global');
  }
};
$hash = static function (array $row): string {
  ksort($row);
  foreach ($row as &$value) {
    $value = $value === NULL ? NULL : (string) $value;
  }
  return hash('sha256', serialize($row));
};
$keyOf = static fn(array $row, array $ids): array => array_intersect_key($row, $ids);
$apply = function ($query, array $conditions) use (&$apply): void {
  foreach ($conditions as $condition) {
    if (isset($condition['conjunction'])) {
      $group = match ($condition['conjunction']) {
        'AND' => $query->andConditionGroup(),
        'OR' => $query->orConditionGroup(),
        default => throw new RuntimeException('Unexpected selection operator.'),
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
// Independently express the approved transformations, without calling transform().
$expected = static function (string $table, array $row, array $spec) use ($data): array {
  foreach ($spec['user_id_columns'] ?? [] as $column) {
    if ((string) ($row[$column] ?? '') === '1') {
      $row[$column] = (string) $data['source_admin_destination_uid'];
    }
  }
  foreach ($spec['polymorphic_user_id_columns'] ?? [] as $column => $typeColumn) {
    if (($row[$typeColumn] ?? NULL) === 'user' && (string) ($row[$column] ?? '') === '1') {
      $row[$column] = (string) $data['source_admin_destination_uid'];
    }
  }
  foreach ($spec['user_path_columns'] ?? [] as $column) {
    if (isset($row[$column])) {
      $row[$column] = preg_replace('@^((?:internal:)?/user/)1(?=/|[?#]|$)@', '${1}' . $data['source_admin_destination_uid'], $row[$column]);
    }
  }
  if ($table === 'key_value' && $row['collection'] === 'pathauto_state.user' && (string) $row['name'] === '1') {
    $row['name'] = (string) $data['source_admin_destination_uid'];
  }
  if ($table === 'wechat_user') {
    foreach (array_keys($row) as $column) {
      if (!in_array($column, ['id', 'uid', 'openid'], TRUE)) {
        $row[$column] = NULL;
      }
    }
  }
  elseif (!empty($spec['constant_columns'])) {
    throw new RuntimeException('Unreviewed constant transformation.');
  }
  if ($table === 'users_data' && $row['name'] === 'otp_user_data' && in_array($row['module'], ['otp_login', 'xinshi_sms'], TRUE)) {
    $metadata = unserialize($row['value'], ['allowed_classes' => FALSE]);
    if (!is_array($metadata)) {
      throw new RuntimeException('Unexpected OTP metadata.');
    }
    $metadata['otps'] = [];
    $metadata['sessions'] = [];
    $metadata['last_otp_time'] = 0;
    $row['value'] = serialize($metadata);
  }
  return $row;
};
try {
  foreach ($data['tables'] as $table => $spec) {
    $beforeErrors = count($errors);
    $targetRows = [];
    $digest = hash_init('sha256');
    $query = $target->select($table, 't')->fields('t', $spec['columns']);
    if ($table === 'key_value') {
      $query->condition('collection', ['pathauto_state.user', 'pathauto_state.node', 'pathauto_state.media', 'pathauto_state.taxonomy_term'], 'IN');
    }
    foreach (array_keys($spec['ids']) as $column) {
      $query->orderBy($column);
    }
    foreach ($query->execute() as $object) {
      $row = (array) $object;
      $rowKey = $hash($keyOf($row, $spec['ids']));
      $checksum = $hash($row);
      if (isset($targetRows[$rowKey])) {
        $fail('duplicate_target_key', $table);
      }
      $targetRows[$rowKey] = $checksum;
      hash_update($digest, $rowKey . $checksum);
    }
    $targetCount = count($targetRows);
    $ledger = [];
    foreach ($target->select('xinshi_migrate_rows', 'l')->fields('l')->condition('table_name', $table)->execute() as $row) {
      $ledger[$row->row_key] = (array) $row;
    }
    $ledgerCount = count($ledger);
    $migration = $manager->createInstance('xinshi_snapshot:' . $table);
    $idMap = $migration->getIdMap();
    $maps = [];
    foreach ($target->select($idMap->mapTableName(), 'm')->fields('m')->execute() as $object) {
      $row = (array) $object;
      $sourceKey = $destinationKey = [];
      foreach (array_keys($spec['ids']) as $i => $column) {
        $sourceKey[$column] = $row['sourceid' . ($i + 1)];
        $destinationKey[$column] = $row['destid' . ($i + 1)];
      }
      $maps[$hash($sourceKey)] = ['destination' => $hash($destinationKey), 'status' => (int) $row['source_row_status']];
    }
    $mapCount = count($maps);
    $preserved = [];
    foreach ($baseline[$table] ?? [] as $row) {
      if ($table === 'users_field_data' && (string) $row['uid'] === '1') {
        $row['mail'] = $data['target_admin_email'];
      }
      $preserved[$hash($keyOf($row, $spec['ids']))] = $hash($row);
    }
    $count = $owned = $anonymous = 0;
    $query = $source->select($table, 's')->fields('s', $spec['columns']);
    $apply($query, $spec['conditions']);
    foreach ($query->execute() as $object) {
      $sourceRow = (array) $object;
      $sourceKey = $hash($keyOf($sourceRow, $spec['ids']));
      $row = $expected($table, $sourceRow, $spec);
      $key = $keyOf($row, $spec['ids']);
      $rowKey = $hash($key);
      $count++;
      if (($maps[$sourceKey]['destination'] ?? NULL) !== $rowKey || ($maps[$sourceKey]['status'] ?? NULL) !== MigrateIdMapInterface::STATUS_IMPORTED) {
        $fail('incorrect_migrate_mapping', $table, $key);
      }
      unset($maps[$sourceKey]);
      if (in_array($table, ['users', 'users_field_data'], TRUE) && (string) $sourceRow['uid'] === '0') {
        $anonymous++;
        continue;
      }
      $owned++;
      $checksum = $hash($row);
      if (($targetRows[$rowKey] ?? NULL) !== $checksum) {
        $fail(isset($targetRows[$rowKey]) ? 'changed_target_values' : 'missing_target_row', $table, $key);
      }
      $entry = $ledger[$rowKey] ?? [];
      $ledgerKey = isset($entry['target_key']) ? unserialize($entry['target_key'], ['allowed_classes' => FALSE]) : NULL;
      if (($entry['dataset'] ?? NULL) !== $data['source_sha256'] || ($entry['checksum'] ?? NULL) !== $checksum || !is_array($ledgerKey) || $hash($ledgerKey) !== $rowKey) {
        $fail('incorrect_batch_ownership', $table, $key);
      }
      unset($targetRows[$rowKey], $ledger[$rowKey]);
    }
    foreach ($preserved as $rowKey => $checksum) {
      if (($targetRows[$rowKey] ?? NULL) !== $checksum) {
        $fail('changed_original_target_row', $table);
      }
      unset($targetRows[$rowKey]);
    }
    if ($targetRows || $ledger || $maps || $count !== $preflight['table_counts'][$table]) {
      $fail('unexpected_row_counts', $table, [], ['remaining_target' => count($targetRows), 'remaining_ledger' => count($ledger), 'remaining_maps' => count($maps), 'source' => $count]);
    }
    $report['tables'][$table] = ['source_rows' => $count, 'target_rows' => $targetCount, 'mapped_rows' => $mapCount, 'owned_rows' => $ledgerCount, 'preserved_rows' => count($preserved), 'anonymous_mapping_only' => $anonymous, 'target_digest' => hash_final($digest), 'errors' => count($errors) - $beforeErrors];
    if (count($report['tables']) % 20 === 0) {
      print json_encode(['tables_verified' => count($report['tables']), 'rows_verified' => array_sum(array_column($report['tables'], 'source_rows')), 'error_count' => count($errors), 'elapsed_seconds' => round(microtime(TRUE) - $started)], JSON_THROW_ON_ERROR) . PHP_EOL;
    }
    unset($migration, $idMap, $targetRows, $ledger, $maps, $query);
    gc_collect_cycles();
  }
  $userCount = (int) $target->query('SELECT COUNT(*) FROM users')->fetchField();
  $check('account_count_including_anonymous', $userCount === 1204, ['actual' => $userCount]);
  $blocked = (int) $target->query('SELECT COUNT(*) FROM users_field_data WHERE uid > 1 AND status = 0 AND default_langcode = 1')->fetchField();
  $check('blocked_accounts_preserved', $blocked === 140, ['actual' => $blocked]);
  $check('distinct_mapped_administrator_and_uid9', (int) $target->query('SELECT COUNT(*) FROM users WHERE uid IN (1, 9, 1403)')->fetchField() === 3);
  $check('subscribe_form_owner', (int) \Drupal::config('webform.webform.subscribe')->get('uid') === 1403);
  $otpCount = 0;
  $otpClean = TRUE;
  foreach ($target->query("SELECT value FROM users_data WHERE name = 'otp_user_data' AND module IN ('otp_login', 'xinshi_sms')") as $row) {
    $metadata = unserialize($row->value, ['allowed_classes' => FALSE]);
    $otpClean = $otpClean && is_array($metadata) && $metadata['otps'] === [] && $metadata['sessions'] === [] && (int) $metadata['last_otp_time'] === 0;
    $otpCount++;
  }
  $check('old_otp_and_session_metadata_cleared', $otpClean && $otpCount === 985, ['records' => $otpCount]);
  $check('bound_wechat_accounts_only', (int) $target->query('SELECT COUNT(*) FROM wechat_user')->fetchField() === 7 && (int) $target->query('SELECT COUNT(*) FROM wechat_user w LEFT JOIN users u ON w.uid = u.uid WHERE u.uid IS NULL OR w.uid = 0')->fetchField() === 0);
  $archive = $read('source-exceptions-archive.private.json');
  $archiveValid = $archive['source_sha256'] === $data['source_sha256'];
  foreach (['path_alias', 'path_alias_revision'] as $table) {
    $rows = $source->select($table, 'a')->fields('a')->condition('id', 245)->execute()->fetchAll(PDO::FETCH_ASSOC);
    $archiveValid = $archiveValid && $rows === $archive['S2'][$table] && (int) $target->select($table, 'a')->condition('id', 245)->countQuery()->execute()->fetchField() === 0;
  }
  $check('S2_original_alias_archived_and_inactive', $archiveValid);
  $floorsValid = TRUE;
  foreach ($data['auto_increment_floors'] as $table => $floor) {
    $actual = (int) $target->query('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', [':table' => $table])->fetchField();
    if ($actual < $floor['next']) {
      $floorsValid = FALSE;
      $fail('unsafe_next_entity_id', $table, [], ['expected_minimum' => $floor['next'], 'actual' => $actual]);
    }
  }
  $check('all_entity_and_revision_id_floors', $floorsValid, ['tables' => count($data['auto_increment_floors'])]);
  $empty = [];
  foreach (['sessions', 'oauth2_token', 'queue', 'batch', 'semaphore'] as $table) {
    if ($target->schema()->tableExists($table)) {
      $empty[$table] = (int) $target->select($table, 't')->countQuery()->execute()->fetchField();
    }
  }
  $originalSessions = [];
  $backup = fopen(Runtime::path('target-before-model.sql'), 'rb');
  while (($line = fgets($backup)) !== FALSE) {
    if (!str_starts_with($line, 'INSERT INTO `sessions` ')) {
      continue;
    }
    if (!preg_match('/^INSERT INTO `sessions` \(([^)]+)\) VALUES \((.+)\);$/D', trim($line), $match)) {
      throw new RuntimeException('Unexpected baseline session record encoding.');
    }
    preg_match_all('/`([^`]+)`/', $match[1], $columns);
    preg_match_all("/NULL|X'([a-f0-9]*)'/", $match[2], $values, PREG_SET_ORDER);
    $record = array_combine($columns[1], array_map(static fn($value) => $value[0] === 'NULL' ? NULL : hex2bin($value[1]), $values));
    $originalSessions[] = $hash($record);
  }
  fclose($backup);
  $currentSessions = [];
  foreach ($target->query('SELECT * FROM sessions') as $session) {
    $currentSessions[] = $hash((array) $session);
  }
  sort($originalSessions);
  sort($currentSessions);
  $check('original_target_sessions_preserved', $originalSessions === $currentSessions, ['original_rows' => count($originalSessions), 'current_rows' => count($currentSessions)]);
  $check('old_source_runtime_state_not_imported', $originalSessions === $currentSessions && array_sum(array_diff_key($empty, ['sessions' => TRUE])) === 0, ['rows' => $empty]);
  $report['checks'] = $checks;
  $report['source_rows'] = array_sum(array_column($report['tables'], 'source_rows'));
  $report['mapped_rows'] = array_sum(array_column($report['tables'], 'mapped_rows'));
  $report['owned_rows'] = array_sum(array_column($report['tables'], 'owned_rows'));
  $report['error_count'] = count($errors);
  $report['status'] = $errors ? 'failed' : 'passed';
}
catch (Throwable $error) {
  $errors[] = ['exception' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()];
  $report['status'] = 'failed';
  $report['error_count'] = count($errors);
}
$report['elapsed_seconds'] = round(microtime(TRUE) - $started, 2);
$report['completed_at'] = gmdate(DATE_ATOM);
file_put_contents(Runtime::path('full-snapshot-validation-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($errors) {
  file_put_contents(Runtime::path('full-snapshot-validation-errors.private.json'), json_encode($errors, JSON_THROW_ON_ERROR));
}
print json_encode(array_diff_key($report, ['tables' => TRUE]), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
if ($report['status'] !== 'passed') {
  exit(1);
}
