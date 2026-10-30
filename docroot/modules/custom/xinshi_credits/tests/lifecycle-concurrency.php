<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

$mode = $argv[1] ?? 'parent';
$clock = new FixtureClock(time());
$context = \Drupal::service('xinshi_credits.context'); $uuid = \Drupal::service('uuid');
$entities = \Drupal::entityTypeManager();
$budgets = new \Drupal\xinshi_credits\CreditBudgets($db, $context, $clock);
$store = new \Drupal\xinshi_credits\CreditStore($db, $context, $clock, $uuid);
$ledger = new \Drupal\xinshi_credits\CreditLedger($db, $context, $uuid, $entities, $budgets, $store);
$life = new \Drupal\xinshi_credits\CreditLifecycle($db, $context, $store, $budgets, $uuid, $entities);

if ($mode === 'worker') {
  $run = $argv[2]; $worker = $argv[3];
  if (!preg_match('/^(distinct|identical|terminal|expiry|shared|returns)$/D', $run)
    || !preg_match('/^[01]$/D', $worker)) throw new RuntimeException('Invalid worker');
  $task = json_decode(file_get_contents($fixture . '/lifecycle-' . $run . '.json'), TRUE)[$worker];
  $clock->now = $task['now'];
  file_put_contents($fixture . '/lifecycle-' . $run . '-' . $worker . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/lifecycle-' . $run . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  try { $result = ['status' => 'committed', 'receipt' => $life->{$task['method']}($task['command'], $task['key'])]; }
  catch (\Drupal\xinshi_credits\CreditException $error) { $result = ['status' => $error->reason]; }
  file_put_contents($fixture . '/lifecycle-' . $run . '-' . $worker . '.result', json_encode($result));
  exit(0);
}

\Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
foreach (['distinct', 'identical', 'terminal', 'expiry', 'shared', 'returns'] as $run) {
  $clock->now = time(); $start = $clock->now;
  $pool = pool('race-' . $run, '1000000', $run === 'shared' ? '11000' : '1000000');
  $bounds = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])->condition('id', $pool)->execute()->fetchField(), TRUE);
  $uids = []; $gifts = []; $common = []; $holds = [];
  for ($n = 0; $n < ($run === 'shared' ? 2 : 1); $n++) {
    $user = \Drupal\user\Entity\User::create(['name' => 'race-' . $run . '-' . $n, 'status' => 1]); $user->save();
    $uids[] = $uid = (string) $user->id();
    $gift = command($pool, 'race-' . $run, $run . '-' . $n, $uid); $gift['expires_at'] = $start + 50;
    $gifts[] = $ledger->grant($gift, 'race-gift-' . $run . '-' . $n);
    $common[] = ['beneficiary_user_id' => $uid, 'actor_user_id' => '1', 'public_reason' => 'Synthetic race'];
    $holds[] = $common[$n] + ['operation_id' => 'race-op-' . $run . '-' . $n, 'quote_id' => 'q1', 'authorization_version' => 'v1',
      'quote_expires_at' => $start + 30, 'category' => 'chat', 'amount_atoms' => '700',
      'cost_bucket_id' => $bounds['chat']['bucket_id'], 'cost_limit_micros' => '750', 'cost_currency' => 'CNY'];
  }
  $finish = $common[0] + ['operation_id' => $holds[0]['operation_id'], 'outcome' => 'completed', 'evidence_ref' => 'final-proof',
    'charged_atoms' => '400', 'actual_cost_micros' => '200', 'cost_status' => 'final', 'cost_currency' => 'CNY'];
  if (in_array($run, ['terminal', 'expiry', 'returns'], TRUE)) $life->reserve($holds[0], 'prepare-' . $run);
  if ($run === 'returns') $life->settle($finish, 'prepare-returns-final');
  $tasks = [];
  for ($n = 0; $n < 2; $n++) {
    $method = 'reserve'; $input = $holds[$run === 'shared' ? $n : 0];
    $key = 'race-' . $run . '-' . ($run === 'identical' ? 0 : $n);
    if ($run === 'distinct') $input['operation_id'] .= '-' . $n;
    if ($run === 'terminal' || $run === 'expiry') {
      $method = 'settle'; $input = $finish;
      if ($n === 1) {
        if ($run === 'terminal') {
          $method = 'release'; $input = $common[0] + ['operation_id' => $holds[0]['operation_id'], 'outcome' => 'not_started', 'evidence_ref' => 'no-start'];
        }
        else { $method = 'expire'; $input = $common[0] + ['grant_id' => $gifts[0]['grant_id']]; }
      }
    }
    if ($run === 'returns') {
      $method = 'returnCredits'; $input = $common[0] + ['operation_id' => $holds[0]['operation_id'], 'return_ref' => 'return-' . $n,
        'returns' => [['grant_id' => $gifts[0]['grant_id'], 'amount_atoms' => '300']]];
    }
    $tasks[] = ['method' => $method, 'command' => $input, 'key' => $key, 'now' => $start + ($run === 'expiry' ? 100 : 0)];
  }
  file_put_contents($fixture . '/lifecycle-' . $run . '.json', json_encode($tasks));
  $processes = [];
  try {
    for ($n = 0; $n < 2; $n++) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql',
      __FILE__, 'worker', $run, (string) $n], [0 => ['file', '/dev/null', 'r'],
        1 => ['file', $fixture . '/lifecycle-race.log', 'a'], 2 => ['file', $fixture . '/lifecycle-race.log', 'a']], $pipes);
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/lifecycle-' . $run . '-0.ready') || !is_file($fixture . '/lifecycle-' . $run . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/lifecycle-' . $run . '.go', 'go');
    foreach ($processes as $process) {
      $status = proc_close($process);
      if ($status !== 0) echo file_get_contents($fixture . '/lifecycle-race.log');
      check($status === 0, 'Lifecycle race worker completes: ' . $run);
    }
    $processes = []; $results = [];
    for ($n = 0; $n < 2; $n++) $results[] = json_decode(file_get_contents($fixture . '/lifecycle-' . $run . '-' . $n . '.result'), TRUE);
    $states = array_column($results, 'status'); sort($states);
    $expected = match ($run) {
      'identical', 'expiry' => ['committed', 'committed'],
      'terminal' => ['committed', 'idempotency_conflict'],
      'shared' => ['committed', 'reward_budget_exhausted'],
      'returns' => ['committed', 'return_exceeded'],
      default => ['committed', 'insufficient_credits'],
    };
    check($states === $expected, 'Serialized outcome: ' . $run . ' ' . json_encode($states));
    if ($run === 'identical') check($results[0]['receipt'] === $results[1]['receipt'], 'Identical concurrent holds replay the exact same receipt');
    if ($run === 'expiry') {
      $grant = $db->select('credit_grant', 'g')->fields('g')->condition('id', $gifts[0]['grant_id'])->execute()->fetchAssoc();
      check((int) $grant['available_atoms'] === 0 && (int) $grant['held_atoms'] === 0
        && (int) $grant['consumed_atoms'] === 400 && (int) $grant['expired_atoms'] === 600, 'Expiry and settlement conserve every authorized atom');
    }
    foreach ($uids as $uid) check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Concurrent credit and budget projections reconstruct: ' . $run);
  }
  finally { foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
}
echo 'Lifecycle concurrency checks: ' . $checks . ' (' . $db->driver() . ')' . PHP_EOL;
