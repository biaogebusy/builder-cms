<?php

declare(strict_types=1);

/** Exercise real Drush bootstrap only from the disposable integration site. */

use Drupal\user\Entity\User;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;
use Symfony\Component\Process\Process;

$cliSettings = Drupal::configFactory()->getEditable('xinshi_knowledge_sync.settings');
$cliSources = $cliSettings->get('sources');
$cliSources[] = ['id' => 'cli-test', 'enabled' => TRUE, 'default_policy' => 'readers',
  'policies' => [['id' => 'readers', 'all_readers' => TRUE, 'roles' => [], 'users' => []]], 'rules' => []];
$cliSettings->set('sources', $cliSources)->save();
$siteRoot = dirname(Drupal::root());
$snapshotPath = $siteRoot . '/xinshi-docs-snapshot.json';
$snapshotJson = json_encode(snapshot('cli-test', ['help.md']), JSON_THROW_ON_ERROR);
file_put_contents($snapshotPath, $snapshotJson);
$runSync = static function (string $path, array $options = ['--source=cli-test', '--account=1'], ?string $cwd = NULL) use ($siteRoot): Process {
  $process = new Process([$siteRoot . '/vendor/bin/drush', '--no-ansi',
    'xinshi-knowledge:sync', $path, ...$options], $cwd ?? $siteRoot, timeout: 30);
  $process->run();
  return $process;
};
$expectStats = static function (Process $process, string $field, int $value): void {
  check($process->isSuccessful() && preg_match('/\b' . $field . '\s+' . $value . '\b/', $process->getOutput()) === 1,
    'Unexpected CLI result: ' . $process->getOutput() . $process->getErrorOutput());
};
$expectFailure = static function (Process $process, string $message): void {
  $output = preg_replace('/\s+/', ' ', $process->getOutput() . $process->getErrorOutput());
  check(!$process->isSuccessful() && str_contains($output, $message), 'Unexpected CLI rejection: ' . $output);
};
$ledgerCount = static fn(): int => (int) Drupal::database()->select(SourceAccess::TABLE, 'd')
  ->condition('source', 'cli-test')->countQuery()->execute()->fetchField();

// Bootstrap changes cwd to docroot; the caller's path must retain its meaning.
$expectStats($runSync('./xinshi-docs-snapshot.json'), 'created', 1);
file_put_contents(Drupal::root() . '/xinshi-docs-snapshot.json', 'Invalid shadow snapshot');
$expectStats($runSync('./xinshi-docs-snapshot.json'), 'created', 1);
mkdir('/xinshi-docs/.knowledge-sync', 0700, TRUE);
file_put_contents('/xinshi-docs/.knowledge-sync/xinshi-docs-snapshot.json', $snapshotJson);
$expectStats($runSync('../xinshi-docs/.knowledge-sync/xinshi-docs-snapshot.json'), 'created', 1);
$expectStats($runSync('../xinshi-docs-snapshot.json', cwd: Drupal::root()), 'created', 1);
$expectStats($runSync($snapshotPath), 'created', 1);
file_put_contents($siteRoot . '/snapshot with spaces.json', $snapshotJson);
$expectStats($runSync('./snapshot with spaces.json'), 'created', 1);
check($ledgerCount() === 0, 'CLI preview wrote source records.');

$expectFailure($runSync($snapshotPath, ['--account=1']), 'The --source option is required.');
$expectFailure($runSync($snapshotPath, ['--source=', '--account=1']), 'The --source option is required.');
$expectFailure($runSync($snapshotPath, ['--source=cli-test']), 'The --account option must be a numeric Drupal user ID.');
$expectFailure($runSync($snapshotPath, ['--source=cli-test', '--account=admin']), 'The --account option must be a numeric Drupal user ID.');
$expectFailure($runSync('https://docs.example.test/snapshot.json'), 'The snapshot must be a local JSON file, not a URL.');
$expectFailure($runSync('./missing.json'), 'Snapshot file not found or not a regular file: ' . $siteRoot . '/missing.json');
$expectFailure($runSync('./docroot'), 'Snapshot file not found or not a regular file: ' . Drupal::root());
$oversizedPath = $siteRoot . '/oversized.json';
$oversized = fopen($oversizedPath, 'w');
ftruncate($oversized, 20 * 1024 * 1024 + 1);
fclose($oversized);
$expectFailure($runSync('./oversized.json'), 'Snapshot file exceeds the 20 MiB limit: ' . $oversizedPath);
$invalidJsonPath = $siteRoot . '/invalid.json';
file_put_contents($invalidJsonPath, '{"documents":[{"text":"SYNTHETIC_PRIVATE_JSON_MARKER"');
$invalidJson = $runSync('./invalid.json', ['--source=cli-test', '--account=1', '--apply']);
$expectFailure($invalidJson, 'Snapshot file is not valid JSON: ' . $invalidJsonPath);
check(!str_contains($invalidJson->getOutput() . $invalidJson->getErrorOutput(), 'SYNTHETIC_PRIVATE_JSON_MARKER'),
  'Invalid JSON error exposed snapshot contents.');
foreach (['', "Exported 1 documents.\n" . $snapshotJson, "\xEF\xBB\xBF" . $snapshotJson] as $invalidContents) {
  file_put_contents($invalidJsonPath, $invalidContents);
  $expectFailure($runSync('./invalid.json', ['--source=cli-test', '--account=1', '--apply']),
    'Snapshot file is not valid JSON: ' . $invalidJsonPath);
}
file_put_contents($invalidJsonPath, 'null');
$expectFailure($runSync('./invalid.json', ['--source=cli-test', '--account=1', '--apply']),
  'Snapshot file must contain a JSON object: ' . $invalidJsonPath);
$expectFailure($runSync($snapshotPath, ['--source=cli-test', '--account=999999999']), 'Unknown import account.');
$cliReader = User::create(['name' => 'cli-reader', 'status' => 1]);
$cliReader->save();
$expectFailure($runSync($snapshotPath, ['--source=cli-test', '--account=' . $cliReader->id(), '--apply']), 'import_forbidden');
check($ledgerCount() === 0, 'Rejected CLI import wrote source records.');

$expectStats($runSync('./xinshi-docs-snapshot.json', ['--source=cli-test', '--account=1', '--apply']), 'created', 1);
check($ledgerCount() === 1, 'CLI apply did not persist the source record.');
$expectStats($runSync($snapshotPath), 'unchanged', 1);
$expectStats($runSync('./xinshi-docs-snapshot.json', ['--source=cli-test', '--account=1', '--apply']), 'unchanged', 1);
