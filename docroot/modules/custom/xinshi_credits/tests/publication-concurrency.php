<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/publication-fixtures.php';

\Drupal::currentUser()->setAccount(user_load_by_name('publisher'));
$publisher = \Drupal::service('xinshi_credits.publication');
if (($argv[1] ?? '') === 'worker') {
  $run = $argv[2]; $worker = $argv[3];
  if (!in_array($run, ['identical', 'overlap', 'quota'], TRUE) || !in_array($worker, ['0', '1'], TRUE)) throw new RuntimeException('Invalid worker');
  $task = json_decode(file_get_contents($fixture . '/publication-' . $run . '.json'), TRUE)[$worker];
  file_put_contents($fixture . '/publication-' . $run . '-' . $worker . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/publication-' . $run . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  try { $result = ['status' => 'committed', 'receipt' => $publisher->publish($task['type'], $task['input'])]; }
  catch (\Drupal\xinshi_credits\CreditException $error) { $result = ['status' => $error->reason]; }
  file_put_contents($fixture . '/publication-' . $run . '-' . $worker . '.result', json_encode($result));
  exit(0);
}
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'publication-race')->save();
$budget = $publisher->publish('budget', budgetInput('quota-budget', 'quota', '3500', '1000'));
foreach (['identical', 'overlap', 'quota'] as $run) {
  $input = budgetInput('race-' . $run, $run); $tasks = [];
  for ($n = 0; $n < 2; $n++) {
    if ($run === 'overlap') $input['publication_ref'] = 'overlap-' . $n;
    if ($run === 'quota') $input = allocationInput('quota-' . $n, [$budget['id']]);
    $tasks[] = ['type' => $run === 'quota' ? 'pool' : 'budget', 'input' => $input];
  }
  file_put_contents($fixture . '/publication-' . $run . '.json', json_encode($tasks));
  $processes = [];
  try {
    for ($n = 0; $n < 2; $n++) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql',
      __FILE__, 'worker', $run, (string) $n], [0 => ['file', '/dev/null', 'r'],
        1 => ['file', $fixture . '/publication-race.log', 'a'], 2 => ['file', $fixture . '/publication-race.log', 'a']], $pipes);
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/publication-' . $run . '-0.ready') || !is_file($fixture . '/publication-' . $run . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/publication-' . $run . '.go', 'go');
    foreach ($processes as $process) {
      $status = proc_close($process);
      if ($status !== 0) echo file_get_contents($fixture . '/publication-race.log');
      check($status === 0, 'Publication worker completes: ' . $run);
    }
    $processes = []; $results = [];
    for ($n = 0; $n < 2; $n++) $results[] = json_decode(file_get_contents($fixture . '/publication-' . $run . '-' . $n . '.result'), TRUE);
    $states = array_column($results, 'status'); sort($states);
    $expected = match ($run) {
      'identical' => ['committed', 'committed'], 'overlap' => ['committed', 'publication_period_overlap'],
      'quota' => ['committed', 'reward_budget_exhausted'],
    };
    check($states === $expected, 'Concurrent publications serialize: ' . $run . ' ' . json_encode($states));
    if ($run === 'identical') check($results[0]['receipt'] === $results[1]['receipt'], 'Concurrent retries return one publication');
  }
  finally { foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
}
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')->save();
echo 'Publication concurrency checks: ' . $checks . PHP_EOL;
