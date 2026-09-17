<?php

/** Run through Drush: php:script /absolute/project/scripts/migration/run.php -- STEP PRIVATE_DIR. */
use Xinshi\Migration\GuardFailure;
use Xinshi\Migration\Runtime;

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit(1);
}
umask(0077);
require_once __DIR__ . '/Runtime.php';
$arguments = $extra ?? array_slice($argv ?? [], 1);
if (($arguments[0] ?? NULL) === '--') {
  array_shift($arguments);
}
$action = $arguments[0] ?? 'help';
$steps = ['preflight', 'backup', 'install-modules', 'adapt-block-body', 'install-config', 'map-config-users', 'validate-model', 'clear-generated-rows', 'import', 'verify', 'verify-entities', 'verify-views', 'verify-access'];
if ($action === 'help') {
  print "Usage: vendor/bin/drush php:script /absolute/project/scripts/migration/run.php -- STEP PRIVATE_BATCH_DIRECTORY\n";
  print 'Steps: status, ' . implode(', ', $steps) . PHP_EOL;
  print "Each invocation runs one explicit step using the configured database connections. No SSH access is required.\n";
  return;
}
try {
  if (!in_array($action, array_merge(['status'], $steps), TRUE) || count($arguments) !== 2) {
    Runtime::reject('expected_a_known_step_and_private_batch_directory');
  }
  Runtime::initialize($arguments[1]);
  Runtime::assertAction($action);
  if ($action === 'status') {
    print json_encode([
      'status' => 'inputs_and_database_identities_verified',
      'execution_environment' => Runtime::environment(),
      'planned_tables' => count(Runtime::plan()['tables']),
      'planned_rows' => Runtime::read('full-snapshot-plan-report.json')['total_rows'],
      'owned_rows' => Runtime::ownedRows(),
      'pending_source_exceptions' => Runtime::pendingExceptions(),
      'business_rows_written' => 0,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
    return;
  }
  if ($action === 'import') {
    // Check the live local connections and model again immediately before writes.
    (static function (): void { require __DIR__ . '/steps/preflight.php'; })();
  }
  (static function (string $step): void { require __DIR__ . '/steps/' . $step . '.php'; })($action);
}
catch (Throwable $error) {
  try {
    Runtime::write('runner-error.private.json', ['step' => $action, 'class' => get_class($error), 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString(), 'recorded_at' => gmdate(DATE_ATOM)]);
  }
  catch (Throwable) {
    // An invalid directory must never cause diagnostics to be written elsewhere.
  }
  print json_encode(['status' => 'failed', 'step' => $action, 'reason' => $error instanceof GuardFailure ? $error->getMessage() : 'execution_failed_see_private_report', 'error_class' => get_class($error)], JSON_THROW_ON_ERROR) . PHP_EOL;
  exit(1);
}
