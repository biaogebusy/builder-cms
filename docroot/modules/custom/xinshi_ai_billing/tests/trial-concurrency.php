<?php

declare(strict_types=1);

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
\Drupal::currentUser()->setAccount(user_load_by_name('trial_admin'));
$setup = \Drupal::service('xinshi_ai_billing.trial');
$missing = array_filter(\Drupal\xinshi_ai_billing\TrialSetup::PERMISSIONS, static fn(string $permission): bool => !\Drupal::currentUser()->hasPermission($permission));
if ($missing) throw new RuntimeException('Synthetic administrator missing: ' . json_encode(['uid' => \Drupal::currentUser()->id(), 'roles' => \Drupal::currentUser()->getRoles(), 'missing' => $missing, 'stored' => \Drupal\user\Entity\Role::load('trial_admin')->getPermissions()]));
if (($argv[1] ?? '') === 'worker') {
  $run = $argv[2]; $worker = $argv[3];
  if (!in_array($run, ['identical', 'changed'], TRUE) || !in_array($worker, ['0', '1'], TRUE)) throw new RuntimeException('Invalid worker');
  $draft = json_decode(file_get_contents($fixture . '/trial-' . $run . '.json'), TRUE)[$worker];
  file_put_contents($fixture . '/trial-' . $run . '-' . $worker . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/trial-' . $run . '.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Barrier timeout');
    usleep(10000);
  }
  try { $setup->save($draft); $result = 'committed'; }
  catch (\Drupal\xinshi_credits\CreditException $error) { $result = $error->reason; }
  file_put_contents($fixture . '/trial-' . $run . '-' . $worker . '.result', $result);
  exit(0);
}
foreach (['identical', 'changed'] as $run) {
  \Drupal::configFactory()->reset();
  $input = \Drupal::config('xinshi_ai_billing.trial')->getRawData(); unset($input['version']);
  $drafts = [$setup->prepare($input, 'trial-race-' . $run, time())];
  $drafts[] = $run === 'identical' ? $drafts[0] : $setup->prepare(array_replace($input, ['daily' => '2']), 'trial-race-other', time());
  file_put_contents($fixture . '/trial-' . $run . '.json', json_encode($drafts));
  $before = (int) $db->select('credit_reward_pool', 'p')->countQuery()->execute()->fetchField();
  $processes = [];
  try {
    for ($n = 0; $n < 2; $n++) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql',
      __FILE__, 'worker', $run, (string) $n], [0 => ['file', '/dev/null', 'r'],
      1 => ['file', $fixture . '/trial-race.log', 'a'], 2 => ['file', $fixture . '/trial-race.log', 'a']], $pipes);
    $deadline = microtime(TRUE) + 15;
    while (!is_file($fixture . '/trial-' . $run . '-0.ready') || !is_file($fixture . '/trial-' . $run . '-1.ready')) {
      if (microtime(TRUE) > $deadline) throw new RuntimeException('Worker bootstrap timeout');
      usleep(10000);
    }
    file_put_contents($fixture . '/trial-' . $run . '.go', 'go');
    foreach ($processes as $process) {
      $status = proc_close($process);
      if ($status !== 0) echo file_get_contents($fixture . '/trial-race.log');
      check($status === 0, 'Trial publisher completes: ' . $run);
    }
    $processes = []; $states = [];
    for ($n = 0; $n < 2; $n++) $states[] = file_get_contents($fixture . '/trial-' . $run . '-' . $n . '.result');
    sort($states);
    check($states === ($run === 'identical' ? ['committed', 'committed'] : ['committed', 'trial_configuration_changed']),
      'Concurrent trial saves preserve one reviewed policy: ' . $run . ' ' . json_encode($states));
    check((int) $db->select('credit_reward_pool', 'p')->countQuery()->execute()->fetchField() === $before + 2,
      'Concurrent setup publishes only one welcome and one daily pool');
  }
  finally { foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
}
echo 'Trial concurrency checks: ' . $checks . PHP_EOL;
