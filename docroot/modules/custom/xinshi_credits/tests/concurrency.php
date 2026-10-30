<?php

declare(strict_types=1);

use Drupal\Core\DrupalKernel;
use Drupal\xinshi_credits\CreditException;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 5);
$fixture = '/tmp/credits-test';
if (getenv('XINSHI_CREDITS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || $root !== '/app' || !is_file($fixture . '/installed')) throw new RuntimeException('Use the isolated runner.');
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Core\\', $root . '/docroot/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $root . '/docroot/core/lib/Drupal/Component');
require_once $root . '/docroot/core/includes/bootstrap.inc';
chdir($root . '/docroot');
$request = Request::create('http://127.0.0.1/');
$kernel = DrupalKernel::createFromRequest($request, $loader, 'prod', FALSE);
$kernel->boot(); $kernel->preHandle($request);
$db = \Drupal::database();
if (!in_array($db->getConnectionOptions()['database'], [$fixture . '/site.sqlite', 'credits_test'], TRUE)) throw new RuntimeException('Unexpected database.');
$ledger = \Drupal::service('xinshi_credits.ledger');
$command = json_decode(file_get_contents($fixture . '/fixtures.json'), TRUE)['race'];
$mode = $argv[1] ?? 'parent';
if ($mode === 'worker') {
  $run = $argv[2]; $worker = $argv[3];
  if (!preg_match('/^(identical|distinct)$/D', $run) || !preg_match('/^[01]$/D', $worker)) throw new RuntimeException('Invalid worker');
  if ($run === 'distinct') $command['source_ref'] .= '-' . $worker;
  file_put_contents($fixture . '/' . $run . '-' . $worker . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/' . $run . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  try {
    $receipt = $ledger->grant($command, 'race-' . $command['source_ref']);
    $result = ['status' => 'committed', 'receipt' => $receipt];
  }
  catch (CreditException $error) { $result = ['status' => $error->reason]; }
  file_put_contents($fixture . '/' . $run . '-' . $worker . '.result', json_encode($result));
  exit(0);
}
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  $GLOBALS['checks']++; echo 'PASS: ' . $message . PHP_EOL;
}
// Use real independent PHP processes and a barrier, not sequential simulated requests.
foreach (['identical', 'distinct'] as $run) {
  if ($run === 'distinct') {
    $pool = $db->select('credit_reward_pool', 'p')->fields('p')->condition('id', $command['budget_pool_id'])->execute()->fetchAssoc();
    $db->update('credit_reward_pool')->fields(['cap_atoms' => 2000])->condition('id', $pool['id'])->execute();
    foreach (json_decode($pool['bounds_json'], TRUE) as $bound) {
      $db->update('credit_budget_bucket')->fields(['cap_micros' => 10000])->condition('id', $bound['bucket_id'])->execute();
    }
  }
  $processes = [];
  try {
    for ($worker = 0; $worker < 2; $worker++) {
      $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', __FILE__, 'worker', $run, (string) $worker],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/race.log', 'a'],
          2 => ['file', $fixture . '/race.log', 'a']], $pipes);
    }
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/' . $run . '-0.ready') || !is_file($fixture . '/' . $run . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/' . $run . '.go', 'go');
    foreach ($processes as $process) check(proc_close($process) === 0, 'Race worker completes: ' . $run);
    $processes = [];
    $results = [];
    for ($worker = 0; $worker < 2; $worker++) $results[] = json_decode(file_get_contents($fixture . '/' . $run . '-' . $worker . '.result'), TRUE);
    if ($run === 'identical') {
      check($results[0]['status'] === 'committed' && $results[1]['status'] === 'committed'
        && $results[0]['receipt'] === $results[1]['receipt'], 'Concurrent identical keys return the same committed receipt');
    }
    else {
      $states = array_column($results, 'status'); sort($states);
      check($states === ['committed', 'reward_budget_exhausted'], 'Concurrent distinct grants cannot exceed the remaining budget');
    }
    $issued = $db->select('credit_reward_pool', 'p')->fields('p', ['issued_atoms'])->condition('id', $command['budget_pool_id'])->execute()->fetchField();
    check((int) $issued === ($run === 'identical' ? 1000 : 2000), 'Race updates pool exactly once per successful grant');
    $grants = $db->select('credit_grant', 'g')->fields('g', ['id', 'journal_id'])
      ->condition('budget_pool_id', $command['budget_pool_id'])->execute()->fetchAll(PDO::FETCH_ASSOC);
    $expected = $run === 'identical' ? 1 : 2;
    check(count($grants) === $expected, 'Race leaves exactly the committed grant batches');
    $postings = $db->select('credit_posting', 'p')->fields('p', ['delta_atoms'])
      ->condition('journal_id', array_column($grants, 'journal_id'), 'IN')->execute()->fetchCol();
    check(count($postings) === $expected * 2 && array_sum($postings) === 0, 'Race preserves balanced journal postings');
    $outbox = $db->select('credit_notification_outbox', 'o')
      ->condition('journal_id', array_column($grants, 'journal_id'), 'IN')->countQuery()->execute()->fetchField();
    check((int) $outbox === $expected, 'Race does not duplicate notification intents');
  }
  finally {
    foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); }
  }
}
echo 'Concurrency checks: ' . $checks . ' (' . $db->driver() . ')' . PHP_EOL;
