<?php

declare(strict_types=1);

use Drupal\user\Entity\User;
use Drupal\xinshi_credits\CreditBudgets;
use Drupal\xinshi_credits\CreditLedger;
use Drupal\xinshi_credits\CreditStore;
use Drupal\xinshi_rewards\Rewards;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';

if (($argv[1] ?? '') === 'worker') {
  $run = $argv[2]; $index = $argv[3];
  if (!in_array($run, ['devices', 'midnight', 'quota'], TRUE) || !in_array($index, ['0', '1'], TRUE)) throw new RuntimeException('Invalid worker');
  $task = json_decode(file_get_contents($fixture . '/reward-' . $run . '.json'), TRUE)[$index];
  \Drupal::currentUser()->setAccount(User::load($task['uid']));
  $clock = new FixtureClock($task['now']);
  $context = \Drupal::service('xinshi_credits.context');
  $ledger = new CreditLedger($db, $context, \Drupal::service('uuid'), \Drupal::entityTypeManager(),
    new CreditBudgets($db, $context, $clock), new CreditStore($db, $context, $clock, \Drupal::service('uuid')));
  $rewards = new Rewards($db, \Drupal::configFactory(), $context, $ledger, $clock, \Drupal::currentUser(),
    \Drupal::entityTypeManager(), \Drupal::service('flood'));
  file_put_contents($fixture . '/reward-' . $run . '-' . $index . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/reward-' . $run . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  // Database busy/deadlock failures are retried with the same client identity.
  for ($attempt = 0; ; $attempt++) {
    try { $result = $rewards->daily($task['key']); break; }
    catch (\Throwable $error) {
      if ($attempt >= 3) throw $error;
      usleep(50000);
    }
  }
  file_put_contents($fixture . '/reward-' . $run . '-' . $index . '.result', json_encode($result));
  exit(0);
}

foreach (['devices', 'midnight', 'quota'] as $run) {
  $tasks = [];
  $user = NULL;
  for ($index = 0; $index < 2; $index++) {
    if (!$user || $run === 'quota') {
      $user = User::create(['name' => 'race-' . $run . '-' . $index, 'status' => 1, 'roles' => ['reward_eligible']]);
      $user->save();
    }
    $tasks[] = ['uid' => (string) $user->id(), 'now' => time() + ($run === 'midnight' ? $index * 86400 : 0),
      'key' => $run === 'midnight' ? 'same-client-key' : 'device-' . $index];
  }
  if ($run === 'quota') {
    $pool = $db->select('credit_reward_pool', 'p')->fields('p')->condition('site_id', 'reward-fixture')->condition('rule_id', 'G11')->execute()->fetchAssoc();
    $db->update('credit_reward_pool')->fields(['cap_atoms' => (int) $pool['issued_atoms'] + 1000])->condition('id', $pool['id'])->execute();
  }
  $before = (int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField();
  file_put_contents($fixture . '/reward-' . $run . '.json', json_encode($tasks));
  $processes = [];
  try {
    for ($index = 0; $index < 2; $index++) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql',
      __FILE__, 'worker', $run, (string) $index], [0 => ['file', '/dev/null', 'r'],
        1 => ['file', $fixture . '/reward-race.log', 'a'], 2 => ['file', $fixture . '/reward-race.log', 'a']], $pipes);
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/reward-' . $run . '-0.ready') || !is_file($fixture . '/reward-' . $run . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/reward-' . $run . '.go', 'go');
    foreach ($processes as $process) {
      $status = proc_close($process);
      if ($status !== 0) echo file_get_contents($fixture . '/reward-race.log');
      check($status === 0, 'Concurrent reward worker completes: ' . $run);
    }
    $processes = [];
    $results = [];
    for ($index = 0; $index < 2; $index++) $results[] = json_decode(file_get_contents($fixture . '/reward-' . $run . '-' . $index . '.result'), TRUE);
    check((int) $db->select('credit_journal', 'j')->condition('site_id', 'reward-fixture')->countQuery()->execute()->fetchField() === $before + 1,
      'Concurrent requests commit one grant: ' . $run);
    if ($run !== 'quota') check($results[0]['receipt'] === $results[1]['receipt'], 'Devices and midnight retries share the original receipt: ' . $run);
    else {
      $statuses = array_column($results, 'status'); sort($statuses);
      check($statuses === ['budget_closed', 'granted'], 'Only one user receives the last budget allocation');
    }
  }
  finally { foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
}
echo 'Reward concurrency checks: ' . $checks . PHP_EOL;
