<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Run the full reviewed core-Migrate plan with private errors and safe progress. */
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\xinshi_migrate\SnapshotPlan;

umask(0077);
$path = Runtime::planPath();
$data = Runtime::plan();
$tableNames = array_keys($data['tables']);
unset($data['tables']);
$preflight = json_decode(file_get_contents(Runtime::path('full-snapshot-plan-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$model = json_decode(file_get_contents(Runtime::path('model-validation-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
$target = Database::getConnection();
$source = Database::getConnection('default', Runtime::sourceKey());
if (($target->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()
  || $preflight['status'] !== 'ready' || $model['status'] !== 'passed'
  || hash_file('sha256', Runtime::path('reference-selected-ids.json')) !== $data['selection_sha256']
  || hash_file('sha256', Runtime::path('model-config-plan.private.json')) !== $data['configuration_sha256']) {
  throw new RuntimeException('The prepared target, checked model, and exact plan inputs are required.');
}
// A buffered source result can take longer to import than the local 420s idle
// timeout. Change only these CLI sessions, not the server or source data.
$target->query('SET SESSION wait_timeout = 7200');
$source->query('SET SESSION wait_timeout = 7200');
$baselinePath = Runtime::path('target-admin-before-full.private.json');
if (!file_exists($baselinePath)) {
  if ((int) $target->query('SELECT COUNT(*) FROM users')->fetchField() !== 2) {
    throw new RuntimeException('Unexpected target users before the initial full import.');
  }
  $baseline = [
    'users' => $target->query('SELECT * FROM users WHERE uid IN (0, 1)')->fetchAll(PDO::FETCH_ASSOC),
    'users_field_data' => $target->query('SELECT * FROM users_field_data WHERE uid IN (0, 1)')->fetchAll(PDO::FETCH_ASSOC),
  ];
  foreach ($tableNames as $table) {
    if (str_starts_with($table, 'user__')) {
      $baseline[$table] = $target->select($table, 'u')->fields('u')->condition('entity_id', [0, 1], 'IN')->execute()->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($table === 'users_data') {
      $baseline[$table] = $target->select($table, 'u')->fields('u')->condition('uid', [0, 1], 'IN')->execute()->fetchAll(PDO::FETCH_ASSOC);
    }
  }
  file_put_contents($baselinePath, json_encode($baseline, JSON_THROW_ON_ERROR));
}
$target->update('users_field_data')->fields(['mail' => $data['target_admin_email']])->condition('uid', 1)->execute();
new Settings(array_replace(Settings::getAll(), ['xinshi_migration_plan_path' => $path]));
$plan = new SnapshotPlan($target, \Drupal::configFactory());
\Drupal::getContainer()->set('xinshi_migrate.plan', $plan);
$plan->validateTarget();
foreach ($data['auto_increment_floors'] as $table => $floor) {
  if (!preg_match('/^[a-z0-9_]+$/D', $table)) {
    throw new RuntimeException('Unexpected counter table.');
  }
  $target->query('ALTER TABLE {' . $table . '} AUTO_INCREMENT = ' . (int) $floor['next']);
}
$archivePath = Runtime::path('source-exceptions-archive.private.json');
if (!file_exists($archivePath)) {
  $archived = [];
  foreach (['path_alias', 'path_alias_revision'] as $table) {
    $archived[$table] = $source->select($table, 'a')->fields('a')->condition('id', 245)->execute()->fetchAll(PDO::FETCH_ASSOC);
  }
  file_put_contents($archivePath, json_encode(['source_sha256' => $data['source_sha256'], 'S2' => $archived], JSON_THROW_ON_ERROR));
}
$manager = \Drupal::service('plugin.manager.migration');
$manager->clearCachedDefinitions();
$messages = new class implements MigrateMessageInterface {
  public int $count = 0;
  public function display($message, $type = 'status') {
    $this->count++;
    file_put_contents(Runtime::path('full-snapshot-messages.private.jsonl'), json_encode(['type' => $type, 'message' => (string) $message], JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND);
  }
};
$reportPath = Runtime::path('full-snapshot-import-report.json');
$report = ['status' => 'running', 'source_sha256' => $data['source_sha256'], 'started_at' => gmdate(DATE_ATOM), 'migrations' => [], 'source_rows' => $preflight['total_rows'], 'messages' => 0, 'source_written' => FALSE, 'execution_environment' => Runtime::environment()];
$completedBeforeResume = [];
if (is_file($reportPath)) {
  $previousReport = json_decode(file_get_contents($reportPath), TRUE, 512, JSON_THROW_ON_ERROR);
  if (in_array($previousReport['status'], ['failed', 'running'], TRUE)) {
    if ($previousReport['source_sha256'] !== $data['source_sha256'] || $previousReport['source_rows'] !== $preflight['total_rows']) {
      throw new RuntimeException('The interrupted run belongs to different snapshot inputs.');
    }
    file_put_contents(Runtime::path('full-snapshot-import-before-resume.json'), json_encode($previousReport, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $report['migrations'] = $previousReport['migrations'];
    $report['previous_elapsed_seconds'] = $previousReport['elapsed_seconds'];
    $report['initial_started_at'] = $previousReport['initial_started_at'] ?? $previousReport['started_at'];
    $completedBeforeResume = $previousReport['migrations'];
  }
}
$report['transaction_scope'] = 'one complete table including core Migrate mappings';
$started = microtime(TRUE);
$processed = 0;
$activeTable = '';
\Drupal::service('event_dispatcher')->addListener(MigrateEvents::POST_ROW_SAVE, function () use (&$processed, &$activeTable, $started): void {
  $processed++;
  if ($processed % 10000 === 0) {
    print json_encode(['rows_processed_this_run' => $processed, 'table' => $activeTable, 'elapsed_seconds' => round(microtime(TRUE) - $started)], JSON_THROW_ON_ERROR) . PHP_EOL;
  }
});
try {
  foreach ($tableNames as $table) {
    $activeTable = $table;
    $tableStarted = microtime(TRUE);
    $migration = $manager->createInstance('xinshi_snapshot:' . $table);
    if ($migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      throw new RuntimeException('Inspect the interrupted migration before resetting its status: ' . $table);
    }
    if (isset($completedBeforeResume[$table])
      && $completedBeforeResume[$table]['result'] === MigrationInterface::RESULT_COMPLETED
      && (int) $migration->getIdMap()->importedCount() === $preflight['table_counts'][$table]) {
      continue;
    }
    // Build map/message tables before the transaction: MySQL DDL commits.
    // The destination's row transactions then nest inside this table batch.
    $migration->getIdMap()->getDatabase();
    $transaction = $target->startTransaction();
    $result = (new MigrateExecutable($migration, $messages))->import();
    $imported = (int) $migration->getIdMap()->importedCount();
    if ($result !== MigrationInterface::RESULT_COMPLETED || $imported !== $preflight['table_counts'][$table]) {
      throw new RuntimeException('Incomplete migration: ' . $table . '. Inspect the private migration messages.');
    }
    unset($transaction);
    $report['migrations'][$table] = ['result' => $result, 'imported' => $imported, 'expected' => $preflight['table_counts'][$table], 'seconds' => round(microtime(TRUE) - $tableStarted, 2)];
    $report['messages'] = $messages->count;
    $report['elapsed_seconds'] = round(microtime(TRUE) - $started);
    file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    if (count($report['migrations']) % 10 === 0) {
      print json_encode(['tables_complete' => count($report['migrations']), 'tables_total' => count($tableNames), 'mapped_rows' => array_sum(array_column($report['migrations'], 'imported')), 'elapsed_seconds' => $report['elapsed_seconds']], JSON_THROW_ON_ERROR) . PHP_EOL;
    }
    unset($migration);
    gc_collect_cycles();
  }
  $report['status'] = 'imported';
  $report['mapped_rows'] = array_sum(array_column($report['migrations'], 'imported'));
  $report['owned_rows'] = (int) $target->query('SELECT COUNT(*) FROM xinshi_migrate_rows')->fetchField();
  $report['target_accounts'] = (int) $target->query('SELECT COUNT(*) FROM users')->fetchField();
  $report['rows_processed_this_run'] = $processed;
  $report['completed_at'] = gmdate(DATE_ATOM);
  $report['elapsed_seconds'] = round(microtime(TRUE) - $started);
  file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
  print json_encode(array_diff_key($report, ['migrations' => TRUE]), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
}
catch (Throwable $error) {
  if (isset($transaction)) {
    $transaction->rollBack();
    unset($transaction);
  }
  $report['status'] = 'failed';
  $report['failed_table'] = $activeTable;
  $report['elapsed_seconds'] = round(microtime(TRUE) - $started);
  file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
  file_put_contents(Runtime::path('full-snapshot-import-error.private.json'), json_encode(['class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()], JSON_THROW_ON_ERROR));
  print json_encode(['status' => 'failed', 'table' => $activeTable, 'details' => 'full-snapshot-import-error.private.json', 'elapsed_seconds' => $report['elapsed_seconds']], JSON_THROW_ON_ERROR) . PHP_EOL;
  exit(1);
}
