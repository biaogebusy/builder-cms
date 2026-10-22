<?php

declare(strict_types=1);

/**
 * @file
 * Exercises actual Office transactions under observed InnoDB row-lock contention.
 */

use Drupal\Core\DrupalKernel;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_office\OfficeException;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
if (getenv('XINSHI_OFFICE_ISOLATED_TEST') !== '1' ||
  getenv('XINSHI_OFFICE_TEST_MYSQL') !== '1' || !is_file('/.dockerenv') || $root !== '/app') {
  throw new RuntimeException('Use the disposable Office MySQL runner only.');
}
if (isset($argv[1])) {
  if (!is_file('/tmp/office-test/mysql-ready') ||
    !preg_match('@^/tmp/office-test/race-[a-zA-Z0-9]+$@D', $argv[1])) {
    throw new RuntimeException('Unexpected worker input.');
  }
  $loader = require $root . '/vendor/autoload.php';
  $loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
  $loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
  require_once $root . '/docroot/core/includes/bootstrap.inc';
  chdir($root . '/docroot');
  $request = Request::create('http://office-test/');
  $kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
  $kernel->boot();
  $kernel->preHandle($request);
  $database = \Drupal::database()->getConnectionOptions();
  if ($database['host'] !== 'office-db' || $database['database'] !== 'office_test') {
    throw new RuntimeException('Unexpected worker database.');
  }
  $job = json_decode(file_get_contents($argv[1]), FALSE, 64, JSON_THROW_ON_ERROR);
  \Drupal::currentUser()->setAccount(User::load($job->actor ?? $job->uid));
  try {
    if (($job->action ?? '') === 'trash') {
      \Drupal::service('xinshi_office.administration')->changeStatus($job->id, TRUE, $job->revision, 0);
      $result = ['trashed' => TRUE];
    }
    else $result = ['receipt' => \Drupal::service('xinshi_office.documents')->write($job->id, $job->input)];
  }
  catch (OfficeException $error) {
    $result = ['error' => $error->getMessage()];
  }
  catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    $result = ['error' => 'confirmation_conflict'];
  }
  file_put_contents($argv[1] . '.result', json_encode($result, JSON_THROW_ON_ERROR));
  exit;
}

require __DIR__ . '/install-fixture.php';
\Drupal::service('module_installer')->install(['xinshi_office']);
touch('/tmp/office-test/mysql-ready');
$checks = 0;
function check(bool $ok, string $label): void {
  if (!$ok) throw new RuntimeException($label);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $label . PHP_EOL;
}
function waitFor(callable $ready, string $label): void {
  $deadline = microtime(TRUE) + 20;
  do {
    if ($ready()) return;
    usleep(100000);
  } while (microtime(TRUE) < $deadline);
  throw new RuntimeException('Timed out: ' . $label);
}
function id(): string { return \Drupal::service('uuid')->generate(); }
function value(array $data): stdClass {
  return json_decode(json_encode($data, JSON_THROW_ON_ERROR), FALSE, 64, JSON_THROW_ON_ERROR);
}
function counts(int $uid): array {
  $result = [];
  foreach (['document', 'revision', 'operation'] as $table) {
    $result[$table] = (int) \Drupal::database()->select('xinshi_office_' . $table, 'o')
      ->condition('uid', $uid)->countQuery()->execute()->fetchField();
  }
  return $result;
}
function race(int $uid, array $jobs): array {
  $observer = new PDO('mysql:host=office-db;dbname=office_test', 'office', 'office-test-only');
  $waits = str_contains($observer->query('SELECT VERSION()')->fetchColumn(), 'MariaDB')
    ? 'SELECT COUNT(DISTINCT requesting_trx_id) FROM information_schema.INNODB_LOCK_WAITS'
    : 'SELECT COUNT(DISTINCT REQUESTING_ENGINE_TRANSACTION_ID) FROM performance_schema.data_lock_waits';
  $transaction = \Drupal::database()->startTransaction();
  \Drupal::service('xinshi_office.access')->lock($uid);
  $workers = [];
  try {
    foreach ($jobs as $job) {
      $file = tempnam('/tmp/office-test', 'race-');
      file_put_contents($file, json_encode(['uid' => $uid] + $job, JSON_THROW_ON_ERROR));
      $process = proc_open([PHP_BINARY, '-d', 'extension=pdo_mysql', __FILE__, $file], [
        0 => ['file', '/dev/null', 'r'], 1 => ['file', $file . '.log', 'w'],
        2 => ['file', $file . '.error', 'w'],
      ], $pipes);
      if (!is_resource($process)) throw new RuntimeException('Cannot start write worker.');
      $workers[] = ['process' => $process, 'file' => $file];
    }
    waitFor(fn() => (int) $observer->query($waits)->fetchColumn() === count($jobs),
      'all writers waiting on the held owner row');
    check(TRUE, 'Observed ' . count($jobs) . ' independent write transactions waiting on the owner row');
    unset($transaction);
    $results = [];
    foreach ($workers as $worker) {
      waitFor(fn() => is_file($worker['file'] . '.result'), 'worker completion');
      $results[] = json_decode(file_get_contents($worker['file'] . '.result'), TRUE, 64, JSON_THROW_ON_ERROR);
    }
    return $results;
  }
  finally {
    unset($transaction);
    foreach ($workers as $worker) {
      if (proc_get_status($worker['process'])['running']) proc_terminate($worker['process']);
      proc_close($worker['process']);
      foreach (['.log', '.error'] as $suffix) {
        $output = file_get_contents($worker['file'] . $suffix);
        if ($output !== '') echo $output;
      }
    }
  }
}

Role::create(['id' => 'office_writer', 'label' => 'Office writer', 'permissions' => [
  'read own office documents', 'edit own office documents',
]])->save();
Role::create(['id' => 'office_manager', 'label' => 'Office manager', 'permissions' => [
  'view all office documents', 'delete office documents',
]])->save();
$manager = User::create(['name' => 'office-race-manager', 'status' => 1, 'roles' => ['office_manager']]);
$manager->save();
$documents = \Drupal::service('xinshi_office.documents');
$settings = \Drupal::configFactory()->getEditable('xinshi_office.settings');
echo 'Database: ' . \Drupal::database()->query('SELECT VERSION()')->fetchField() . PHP_EOL;
foreach (['word', 'sheet'] as $kind) {
  $user = User::create(['name' => 'office-race-' . $kind, 'status' => 1, 'roles' => ['office_writer']]);
  $user->save();
  $uid = (int) $user->id();
  \Drupal::currentUser()->setAccount($user);
  $content = $kind === 'word' ? ['version' => 1, 'type' => 'doc', 'content' => [
    ['type' => 'paragraph', 'attrs' => ['id' => 'p1'], 'content' => [['type' => 'text', 'text' => '并发样例']]],
  ]] : ['version' => 1, 'type' => 'workbook', 'dateSystem' => '1904', 'styles' => (object) [],
    'sheets' => [['id' => 'data', 'name' => 'Data', 'rows' => 1000, 'columns' => 26,
      'defaultRowHeight' => 15, 'defaultColumnWidth' => 8.43, 'rowSizes' => [], 'columnSizes' => [],
      'merges' => [], 'freeze' => ['rows' => 0, 'columns' => 0], 'cells' => [
        ['row' => 0, 'column' => 0, 'value' => ['type' => 'text', 'value' => '00123']],
      ]]]];
  $input = value(['operationId' => id(), 'kind' => $kind, 'name' => 'Concurrent ' . $kind,
    'content' => $content, 'assetIds' => [], 'source' => 'manual', 'chatId' => NULL,
    'originalFileId' => NULL, 'importReport' => NULL]);
  $job = ['id' => NULL, 'input' => $input];
  $created = race($uid, [$job, $job]);
  check(isset($created[0]['receipt']) && $created[0] === $created[1], "$kind duplicate creates return one receipt");
  check(counts($uid) === ['document' => 1, 'revision' => 1, 'operation' => 1], "$kind duplicate create is atomic");
  $doc = $created[0]['receipt']['documentId'];
  $history = $documents->read($doc, 1);
  $jobs = [];
  foreach (['A', 'B'] as $name) {
    $jobs[] = ['id' => $doc, 'input' => value(['operationId' => id(), 'name' => $name,
      'content' => $content, 'assetIds' => [], 'source' => 'ai', 'expectedRevision' => 1])];
  }
  $updated = race($uid, $jobs);
  $winner = isset($updated[0]['receipt']) ? 0 : 1;
  check(($updated[$winner]['receipt']['revision'] ?? NULL) === 2 &&
    $updated[1 - $winner] === ['error' => 'revision_conflict'], "$kind competing updates have exactly one winner");
  check(counts($uid) === ['document' => 1, 'revision' => 2, 'operation' => 2], "$kind losing update creates no partial rows");
  $receipt = $updated[$winner]['receipt'];
  check($documents->find($jobs[$winner]['input']->operationId) === $receipt,
    "$kind ignored response recovers the exact receipt");
  $settings->set('write_types', [])->save();
  check($documents->write($doc, $jobs[$winner]['input']) === $receipt,
    "$kind disabled writes still recover the original duplicate operation");
  check($documents->read($doc, 1)['content'] == $history['content'], "$kind historical content is immutable");
  $settings->set('write_types', ['word', 'sheet'])->save();
  $database = \Drupal::database();
  $database->query("CREATE TRIGGER office_fail_receipt BEFORE INSERT ON xinshi_office_operation FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic_receipt_failure'");
  $failed = clone $jobs[$winner]['input']; $failed->operationId = id(); $failed->expectedRevision = 2;
  try {
    $documents->write($doc, $failed);
    throw new RuntimeException('Expected receipt insert failure.');
  }
  catch (Throwable $error) {
    check(str_contains($error->getMessage(), 'synthetic_receipt_failure'), "$kind real SQL fault reaches receipt insertion");
  }
  finally { $database->query('DROP TRIGGER office_fail_receipt'); }
  check($documents->read($doc)['revision'] === 2 && $documents->find($failed->operationId) === NULL &&
    counts($uid) === ['document' => 1, 'revision' => 2, 'operation' => 2], "$kind SQL failure rolls back pointer, revision and receipt");
  $settings->set('max_documents', 2)->save();
  $left = clone $input; $left->operationId = id();
  $right = clone $input; $right->operationId = id();
  $quota = race($uid, [['id' => NULL, 'input' => $left], ['id' => NULL, 'input' => $right]]);
  $winner = isset($quota[0]['receipt']) ? 0 : 1;
  check(isset($quota[$winner]['receipt']) && $quota[1 - $winner] === ['error' => 'storage_limit'],
    "$kind concurrent creations respect the last available document slot");
  check(counts($uid) === ['document' => 2, 'revision' => 3, 'operation' => 3], "$kind quota failure leaves no partial rows");
  $edit = clone $jobs[0]['input']; $edit->operationId = id(); $edit->expectedRevision = 2;
  $lifecycle = race($uid, [['id' => $doc, 'input' => $edit],
    ['id' => $doc, 'actor' => (int) $manager->id(), 'action' => 'trash', 'revision' => 2]]);
  check((isset($lifecycle[0]['receipt']) && $lifecycle[1] === ['error' => 'confirmation_conflict']) ||
    ($lifecycle[0] === ['error' => 'document_unavailable'] && $lifecycle[1] === ['trashed' => TRUE]),
    "$kind concurrent edit and deletion cannot both succeed from the same confirmation");
  check($documents->read($doc, 1)['content'] == $history['content'], "$kind deletion preserves historical snapshots");
  check($documents->find($receipt['operationId']) === $receipt,
    "$kind deletion preserves the exact submitted-write receipt");
  $settings->set('max_documents', 100)->save();
}
echo 'Passed ' . $checks . ' MySQL concurrency checks.' . PHP_EOL;
