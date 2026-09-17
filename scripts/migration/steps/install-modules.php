<?php

use Xinshi\Migration\Runtime;

Runtime::requireEntryPoint();

/** Install the configuration providers and the snapshot runner in the target. */
use Drupal\Core\Database\Database;

umask(0077);
$db = Database::getConnection();
if (($db->getConnectionOptions()['database'] ?? '') !== Runtime::targetDatabase() || !file_exists(Runtime::path('target-before-model-backup.json'))) {
  throw new RuntimeException('The prepared target and its baseline backup are required.');
}
$plan = json_decode(file_get_contents(Runtime::path('model-config-plan-report.json')), TRUE, 512, JSON_THROW_ON_ERROR);
if ($plan['missing_modules'] || $plan['missing_dependencies']) {
  throw new RuntimeException('Resolve the model plan dependencies before installation.');
}
$before = array_keys(\Drupal::moduleHandler()->getModuleList());
try {
  // The snapshot runner has no source configuration, so it is not discovered
  // from the configuration plan. A fresh target still needs its schema and
  // core Migrate dependencies before any placeholder cleanup or data import.
  $migrationModules = ['migrate', 'migrate_tools', 'xinshi_migrate'];
  $required = array_unique(array_merge($plan['modules'], $migrationModules));
  $modules = array_values(array_diff($required, $before));
  \Drupal::service('module_installer')->install($modules, TRUE);
  $after = array_keys(\Drupal::moduleHandler()->getModuleList());
  if (array_diff($required, $after) || !Database::getConnection()->schema()->tableExists('xinshi_migrate_rows')) {
    throw new RuntimeException('The model providers and snapshot ownership schema must be installed.');
  }
  $report = ['status' => 'installed', 'newly_installed' => array_values(array_diff($after, $before)), 'enabled_module_count' => count($after), 'target_accounts' => (int) Database::getConnection()->query('SELECT COUNT(*) FROM users')->fetchField(), 'legacy_binding_entity' => \Drupal::entityTypeManager()->hasDefinition('wechat_user')];
  file_put_contents(Runtime::path('model-modules-install-report.json'), json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
  print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
}
catch (Throwable $error) {
  file_put_contents(Runtime::path('model-modules-install-error.private.json'), json_encode(['class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString()], JSON_THROW_ON_ERROR));
  print json_encode(['status' => 'failed', 'error_class' => get_class($error), 'details' => 'model-modules-install-error.private.json'], JSON_THROW_ON_ERROR) . PHP_EOL;
  exit(1);
}
