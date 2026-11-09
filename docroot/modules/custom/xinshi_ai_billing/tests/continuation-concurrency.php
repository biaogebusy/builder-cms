<?php

declare(strict_types=1);

use Drupal\xinshi_ai_billing\BillingProfiles;
use Drupal\xinshi_credits\CreditException;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
require __DIR__ . '/assembly.php';
$user = user_load_by_name('ai_credit_user');
\Drupal::currentUser()->setAccount($user);
$billing = fixtureBilling();
$producer = ['producer_id' => 'fixture-node', 'site_id' => 'fixture'];
if (($argv[1] ?? '') === 'worker') {
  $scenario = $argv[2]; $worker = $argv[3];
  $command = json_decode(file_get_contents($fixture . '/finance-' . $scenario . '.json'), TRUE)[$worker];
  file_put_contents($fixture . '/finance-' . $scenario . '-' . $worker . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/finance-' . $scenario . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  try {
    $result = $command['action'] === 'authorize' ? $billing->authorize($command['input'])
      : $billing->{$command['action']}($producer, $command['input']);
    $outcome = 'committed';
  }
  catch (CreditException $error) { $outcome = $error->reason; }
  file_put_contents($fixture . '/finance-' . $scenario . '-' . $worker . '.result', $outcome);
  exit(0);
}

function financeSource(string $root, string $attempt): void {
  global $user, $db;
  \Drupal::moduleHandler()->loadInclude('xinshi_ai_usage', 'install');
  $fields = ['site_id' => 'fixture', 'operation_id' => $root, 'attempt_id' => $attempt,
    'logical_call_id' => $attempt, 'producer_id' => 'fixture-node', 'actor_user_id' => (string) $user->id(),
    'state' => 'not_sent', 'dispatch_state' => 'not_sent', 'usage_revision' => 1];
  foreach (xinshi_ai_usage_schema()['ai_provider_attempt']['fields'] as $key => $definition) {
    if (array_key_exists($key, $fields) || $definition['type'] === 'serial' || !($definition['not null'] ?? FALSE)
      || array_key_exists('default', $definition)) continue;
    $fields[$key] = $definition['type'] === 'int' ? 1 : 'synthetic';
  }
  $db->insert('ai_provider_attempt')->fields($fields)->execute();
}

foreach (['approve-approve', 'approve-cancel', 'permit-cancel'] as $scenario) {
  $root = 'finance-race-' . $scenario;
  $request = ['feature' => 'text', 'model' => 'fixture-model', 'platform' => 'fixture',
    'request_hash' => str_repeat('a', 64), 'producer_id' => 'fixture-node'];
  $quote = $billing->quote($request, $root);
  $billing->authorize(['operation_id' => $root, 'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']]);
  $call = ['operation_id' => $root, 'attempt_id' => $root . '-first', 'model' => 'fixture-model',
    'account_ref' => 'fixture', 'role' => 'primary'];
  $billing->permit($producer, $call); financeSource($root, $call['attempt_id']);
  $link = ['root_operation_id' => $root, 'parent_operation_id' => $root];
  $next = array_replace($request, ['continuation' => $link,
    'request_hash' => hash('sha256', BillingProfiles::json(['version' => 1, 'request_hash' => $request['request_hash']] + $link))]);
  $commands = [];
  for ($i = 0; $i < 2; $i++) {
    $quote = $billing->quote($next, $root . '-' . $i);
    $commands[] = ['action' => 'authorize', 'input' => ['operation_id' => $root . '-auth-' . $i,
      'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']]];
  }
  $final = ['operation_id' => $root, 'delivery' => 'none', 'delivery_ref' => 'synthetic:' . $root,
    'attempts' => [['attempt_id' => $call['attempt_id'], 'revision' => 1]]];
  if ($scenario === 'permit-cancel') {
    $billing->authorize($commands[0]['input']);
    $call['authorization_id'] = $commands[0]['input']['operation_id'];
    $call['attempt_id'] = $root . '-second'; financeSource($root, $call['attempt_id']);
    $commands[0] = ['action' => 'permit', 'input' => $call];
    $final['attempts'][] = ['attempt_id' => $call['attempt_id'], 'revision' => 1];
  }
  if ($scenario !== 'approve-approve') $commands[1] = ['action' => 'finalize', 'input' => $final];
  file_put_contents($fixture . '/finance-' . $scenario . '.json', json_encode($commands));
  $processes = [];
  try {
    for ($i = 0; $i < 2; $i++) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql',
      __FILE__, 'worker', $scenario, (string) $i], [0 => ['file', '/dev/null', 'r'],
      1 => ['file', $fixture . '/finance-race.log', 'a'], 2 => ['file', $fixture . '/finance-race.log', 'a']], $pipes);
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/finance-' . $scenario . '-0.ready') || !is_file($fixture . '/finance-' . $scenario . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/finance-' . $scenario . '.go', 'go');
    foreach ($processes as $process) {
      $status = proc_close($process);
      if ($status !== 0) echo file_get_contents($fixture . '/finance-race.log');
      check($status === 0, 'Financial race worker finishes: ' . $scenario);
    }
    $processes = [];
    $outcomes = [];
    for ($i = 0; $i < 2; $i++) $outcomes[] = file_get_contents($fixture . '/finance-' . $scenario . '-' . $i . '.result');
    check(in_array('committed', $outcomes, TRUE) && !array_diff($outcomes, ['committed', 'continuation_conflict', 'operation_closed']),
      'Financial race has only serialized valid outcomes: ' . $scenario);
    $links = (int) $db->select('ai_billing_continuation', 'c')->condition('root_operation_id', $root)->countQuery()->execute()->fetchField();
    check($links <= 1 && ($scenario !== 'approve-approve' || $links === 1), 'One parent creates at most one additional hold');
    $result = $billing->finalize($producer, $final);
    check($result['status'] === 'released' && $result['charged_atoms'] === '0', 'Cancellation reconciles every concurrent hold');
    check(\Drupal::service('xinshi_credits.audit')->inspect((string) $user->id())['status'] === 'complete', 'Concurrent ledger reconstructs');
  }
  finally { foreach ($processes as $process) { proc_terminate($process); proc_close($process); } }
}
echo 'AI continuation concurrency checks: ' . $checks . PHP_EOL;
