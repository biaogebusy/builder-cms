<?php

/** Integration checks against an already imported local rehearsal, without writes. */
use Drupal\Core\Database\Database;
use Xinshi\Migration\GuardFailure;
use Xinshi\Migration\Runtime;

if (PHP_SAPI !== 'cli') {
  exit(1);
}
umask(0077);
require_once __DIR__ . '/Runtime.php';
$arguments = $extra ?? [];
if (($arguments[0] ?? NULL) === '--') {
  array_shift($arguments);
}
if (count($arguments) !== 1) {
  throw new RuntimeException('Pass the private local rehearsal batch directory.');
}
Runtime::initialize($arguments[0]);
if (Runtime::environment() !== 'local' || Runtime::ownedRows() === 0 || !Runtime::pendingExceptions()) {
  throw new RuntimeException('Use an imported local rehearsal with pending exception decisions.');
}
$initialOwned = Runtime::ownedRows();
$manifest = Runtime::read('input-manifest.json');
$runtimeSettings = Runtime::read('batch-runtime.json');
$checks = [];
$rejects = static function (string $name, string $expected, callable $action) use (&$checks): void {
  try {
    $action();
    $checks[$name] = FALSE;
  }
  catch (GuardFailure $error) {
    $checks[$name] = $error->getMessage() === $expected;
  }
};
foreach (['backup', 'install-modules', 'adapt-block-body', 'install-config', 'map-config-users', 'clear-generated-rows'] as $step) {
  $rejects('populated_target_rejects_' . $step, 'refusing_to_reinitialize_a_populated_target', static fn() => Runtime::assertAction($step));
}
$rejects('pending_exceptions_reject_import', 'source_exception_decisions_are_pending', static fn() => Runtime::assertAction('import'));
$scratch = rtrim(sys_get_temp_dir(), '/') . '/xinshi-runner-guards-' . bin2hex(random_bytes(8));
mkdir($scratch, 0700);
try {
  foreach (array_merge(array_keys($manifest['files']), ['input-manifest.json', 'batch-runtime.json']) as $name) {
    copy(Runtime::path($name), $scratch . '/' . $name);
  }
  $changed = $runtimeSettings;
  $changed['target_database'] .= '_wrong';
  file_put_contents($scratch . '/batch-runtime.json', json_encode($changed, JSON_THROW_ON_ERROR));
  $rejects('wrong_database_rejected', 'target_database_identity_mismatch', static fn() => Runtime::initialize($scratch));

  file_put_contents($scratch . '/batch-runtime.json', json_encode($runtimeSettings, JSON_THROW_ON_ERROR));
  file_put_contents($scratch . '/reference-selected-ids.json', '{}');
  $rejects('altered_input_rejected', 'reviewed_input_hash_mismatch', static fn() => Runtime::initialize($scratch));
  copy($arguments[0] . '/reference-selected-ids.json', $scratch . '/reference-selected-ids.json');

  $changedPlan = json_decode(file_get_contents($scratch . '/full-snapshot-plan.private.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  $changedPlan['target_uuid'] = '00000000-0000-0000-0000-000000000000';
  file_put_contents($scratch . '/full-snapshot-plan.private.json', json_encode($changedPlan, JSON_THROW_ON_ERROR));
  $changedManifest = $manifest;
  $changedManifest['files']['full-snapshot-plan.private.json'] = hash_file('sha256', $scratch . '/full-snapshot-plan.private.json');
  file_put_contents($scratch . '/input-manifest.json', json_encode($changedManifest, JSON_THROW_ON_ERROR));
  $rejects('wrong_site_uuid_rejected', 'target_site_identity_mismatch', static fn() => Runtime::initialize($scratch));
  $rejects('public_batch_directory_rejected', 'batch_directory_is_inside_public_docroot', static fn() => Runtime::initialize(\Drupal::root()));
}
finally {
  foreach (glob($scratch . '/*') ?: [] as $file) {
    unlink($file);
  }
  if (is_file($scratch . '/.execution.lock')) {
    unlink($scratch . '/.execution.lock');
  }
  rmdir($scratch);
}
Runtime::initialize($arguments[0]);
$checks['batch_ownership_count_unchanged'] = Runtime::ownedRows() === $initialOwned;
$report = ['status' => in_array(FALSE, $checks, TRUE) ? 'failed' : 'passed', 'checks' => $checks, 'business_rows_written' => 0];
Runtime::write('runner-guard-validation.json', $report);
print json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
if ($report['status'] !== 'passed') {
  exit(1);
}
