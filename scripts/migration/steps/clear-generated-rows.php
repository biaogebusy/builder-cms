<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/**
 * Remove only placeholders created by this rehearsal's module/config install.
 * Original target tables must have contained zero rows for every affected table.
 */
use Drupal\Core\Database\Database;

umask(0077);
$db = Database::getConnection();
if (($db->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()) {
  throw new RuntimeException('This preparation is restricted to the prepared target.');
}
$backup = Runtime::path('target-before-model.sql');
$backupReport = json_decode(file_get_contents(Runtime::path('target-before-model-backup.json')), TRUE, 512, JSON_THROW_ON_ERROR);
if (hash_file('sha256', $backup) !== $backupReport['sha256']) {
  throw new RuntimeException('The baseline recovery point has changed.');
}
$tables = ['consumer_field_data', 'consumer', 'path_alias_revision', 'path_alias', 'webform'];
$baselineCounts = array_fill_keys($tables, 0);
$stream = fopen($backup, 'rb');
while (($line = fgets($stream)) !== FALSE) {
  foreach ($tables as $table) {
    if (str_starts_with($line, 'INSERT INTO ' . chr(96) . $table . chr(96) . ' ')) {
      $baselineCounts[$table]++;
    }
  }
}
fclose($stream);
if (array_sum($baselineCounts) !== 0) {
  throw new RuntimeException('Original target data exists; placeholder cleanup is not allowed.');
}
if ((int) $db->query('SELECT COUNT(*) FROM xinshi_migrate_rows')->fetchField() !== 0) {
  throw new RuntimeException('Snapshot data already exists; model preparation cannot run again.');
}
$rows = [];
foreach ($tables as $table) {
  $rows[$table] = $db->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
}
$archive = Runtime::path('generated-model-rows.private.json');
if (file_exists($archive)) {
  if (array_sum(array_map('count', $rows)) !== 0) {
    throw new RuntimeException('Generated rows reappeared; compare the original preparation archive before retrying.');
  }
  print json_encode(['status' => 'already_prepared']) . PHP_EOL;
  return;
}
file_put_contents($archive, json_encode($rows, JSON_THROW_ON_ERROR));
$transaction = $db->startTransaction();
try {
  foreach ($tables as $table) {
    $db->delete($table)->execute();
  }
  unset($transaction);
}
catch (Throwable $error) {
  $transaction->rollBack();
  throw $error;
}
$report = ['status' => 'prepared', 'original_target_rows' => $baselineCounts, 'removed_generated_rows' => array_map('count', $rows), 'archive_sha256' => hash_file('sha256', $archive), 'target_accounts' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchField()];
file_put_contents(Runtime::path('generated-model-rows-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
