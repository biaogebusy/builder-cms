<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Private, complete SQL recovery point before installing the rehearsal model. */
use Drupal\Core\Database\Database;

umask(0077);
$db = Database::getConnection();
if (($db->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase()) {
  throw new RuntimeException('Only the prepared target may be backed up here.');
}
$path = Runtime::path('target-before-model.sql');
$reportPath = Runtime::path('target-before-model-backup.json');
if (file_exists($path)) {
  if (!file_exists($reportPath)) {
    throw new RuntimeException('Incomplete backup exists; inspect before proceeding.');
  }
  print file_get_contents($reportPath) . PHP_EOL;
  return;
}
$file = fopen($path, 'xb');
fwrite($file, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
$counts = [];
foreach ($db->query('SHOW TABLES')->fetchCol() as $table) {
  if (!preg_match('/^[a-z0-9_]+$/D', $table)) {
    throw new RuntimeException('Unexpected table identifier.');
  }
  $create = array_values($db->query('SHOW CREATE TABLE `' . $table . '`')->fetchAssoc())[1];
  fwrite($file, "DROP TABLE IF EXISTS `$table`;\n" . $create . ";\n");
  $counts[$table] = 0;
  foreach ($db->query('SELECT * FROM `' . $table . '`') as $object) {
    $row = (array) $object;
    $names = implode(',', array_map(fn($column) => '`' . $column . '`', array_keys($row)));
    $values = implode(',', array_map(fn($value) => $value === NULL ? 'NULL' : "X'" . bin2hex((string) $value) . "'", $row));
    fwrite($file, "INSERT INTO `$table` ($names) VALUES ($values);\n");
    $counts[$table]++;
  }
}
fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($file);
$report = ['file' => basename($path), 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path), 'tables' => count($counts), 'rows' => array_sum($counts), 'user_accounts' => $counts['users'], 'target_uuid' => Runtime::plan()['target_uuid'], 'target_database' => Runtime::targetDatabase(), 'source_sha256' => Runtime::plan()['source_sha256']];
file_put_contents($reportPath, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
