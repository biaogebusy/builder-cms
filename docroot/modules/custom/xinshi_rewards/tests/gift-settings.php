<?php

declare(strict_types=1);

use Drupal\user\Entity\User;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, (string) $error . PHP_EOL); exit(1); });
if (($argv[1] ?? '') === 'worker') {
  $index = $argv[2];
  if (!in_array($index, ['0', '1'], TRUE)) throw new RuntimeException('Invalid gift worker');
  $task = json_decode(file_get_contents($fixture . '/gift-race.json'), TRUE)[$index];
  \Drupal::currentUser()->setAccount(User::load(1));
  file_put_contents($fixture . '/gift-race-' . $index . '.ready', 'ready');
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/gift-race.go')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Gift barrier timeout');
    usleep(10000);
  }
  for ($attempt = 0; ; $attempt++) {
    try { $result = \Drupal::service('xinshi_rewards.rewards')->gift($task, 'gift-race-' . $index)['status']; break; }
    catch (\Drupal\xinshi_credits\CreditException $error) { $result = $error->reason; break; }
    catch (Throwable $error) { if ($attempt >= 3) throw $error; usleep(50000); }
  }
  file_put_contents($fixture . '/gift-race-' . $index . '.result', $result);
  exit(0);
}
$giftConfig = \Drupal::configFactory();
$savedCredits = $giftConfig->get('xinshi_credits.settings')->getRawData();
$savedRewards = $giftConfig->get('xinshi_rewards.settings')->getRawData();
$giftConfig->getEditable('xinshi_credits.settings')->set('site_id', 'gift-points-fixture')->set('grants_enabled', TRUE)->save();
$giftConfig->getEditable('xinshi_rewards.settings')->set('points_pool_ids', [])->set('admin_gift_pool_id', '')->set('admin_gifts_enabled', FALSE)->save();
$principal = \Drupal::currentUser(); $principal->setAccount(User::load(1));
$recipient = User::create(['name' => 'gift_points_recipient', 'status' => 1]); $recipient->save();
$setup = \Drupal::service('xinshi_rewards.gift_setup');
$rewards = \Drupal::service('xinshi_rewards.rewards');
$publication = \Drupal::service('xinshi_credits.publication');
$input = ['cap_atoms' => '2000', 'grant_limit_atoms' => '1000', 'scope' => ['chat', 'image'],
  'period_end' => time() + 86400, 'expires_at' => NULL];
$before = snapshot();
$draft = $setup->prepare($input, 'gift-limited', time());
check(snapshot() === $before && !$setup->current()['pool'], 'Review does not publish a missing G12 or issue credits');
denied(fn() => $setup->prepare($input + ['rule_id' => 'G11'], 'override', time()), 'invalid_fields');
foreach (['0', '-1', '0.1', '', 'unlimited', 1000, FALSE] as $invalid) {
  denied(fn() => $setup->prepare(array_replace($input, ['cap_atoms' => $invalid]), 'invalid', time()), 'invalid_amount');
}
$principal->setAccount(user_load_by_name('reward_admin'));
denied(fn() => $setup->current(), 'permission_denied');
denied(fn() => $setup->save($draft), 'permission_denied');
$principal->setAccount(User::load(1));
$failure = static function (\Drupal\Core\Config\ConfigCrudEvent $event): void {
  if ($event->getConfig()->getName() === 'xinshi_rewards.settings') throw new RuntimeException('Synthetic gift config failure');
};
$dispatcher = \Drupal::service('event_dispatcher'); $dispatcher->addListener(\Drupal\Core\Config\ConfigEvents::SAVE, $failure);
try { $setup->save($draft); throw new RuntimeException('Expected failed configuration'); }
catch (RuntimeException $error) { check($error->getMessage() === 'Synthetic gift config failure' && snapshot() === $before, 'Failed activation rolls back its publication'); }
finally { $dispatcher->removeListener(\Drupal\Core\Config\ConfigEvents::SAVE, $failure); }
$setup->save($draft); $published = snapshot(); $setup->save($draft);
check(snapshot() === $published, 'Repeated publication preserves one rule and no gifts');
$tampered = $draft; $tampered['input']['cap_atoms'] = '3000';
denied(fn() => $setup->save($tampered), 'idempotency_conflict');
$gift = ['beneficiary_user_id' => (string) $recipient->id(), 'amount_atoms' => '1000',
  'budget_pool_id' => $setup->current()['pool']['id'], 'scope' => ['chat', 'image'],
  'expires_at' => NULL, 'public_reason' => 'Synthetic gift limit test'];
$first = $rewards->gift($gift, 'gift-limited-1');
check($rewards->gift($gift, 'gift-limited-1') === $first, 'Repeated gift returns the same financial receipt');
denied(fn() => $rewards->gift(array_replace($gift, ['amount_atoms' => '1001']), 'gift-too-large'), 'invalid_grant_policy');
$setup->setEnabled(FALSE, $gift['budget_pool_id']);
check($rewards->gift($gift, 'gift-limited-1') === $first, 'Paused gift replays the accepted receipt');
denied(fn() => $rewards->giftPolicy((string) $recipient->id()), 'admin_gifts_disabled');
$setup->setEnabled(TRUE, $gift['budget_pool_id']);
check($rewards->giftPolicy((string) $recipient->id())['remaining_atoms'] === '1000', 'Resume retains issued credits');
$rewards->gift($gift, 'gift-limited-2');
denied(fn() => $rewards->gift($gift, 'gift-exhausted'), 'reward_budget_exhausted');
check($rewards->giftPolicy((string) $recipient->id())['remaining_atoms'] === '0', 'Finite pool reports exact exhaustion');

foreach (['single-only' => [NULL, '1000'], 'total-only' => ['2000', NULL], 'unlimited' => [NULL, NULL]] as $name => [$cap, $limit]) {
  $draft = $setup->prepare(array_replace($input, ['cap_atoms' => $cap, 'grant_limit_atoms' => $limit]), 'gift-' . $name, time());
  $setup->save($draft);
  $policy = $rewards->giftPolicy((string) $recipient->id());
  check($policy['grant_limit_atoms'] === $limit && $policy['remaining_atoms'] === $cap, 'Independent optional limits: ' . $name);
  $gift['budget_pool_id'] = $policy['budget_pool_id'];
  $gift['amount_atoms'] = $limit ?? '2000';
  $rewards->gift($gift, 'gift-' . $name);
  if ($cap !== NULL) denied(fn() => $rewards->gift($gift, 'gift-total-exhausted'), 'reward_budget_exhausted');
  if ($limit !== NULL) denied(fn() => $rewards->gift(array_replace($gift, ['amount_atoms' => '1001']), 'gift-single-exceeded'), 'invalid_grant_policy');
  else check($rewards->giftPolicy((string) $recipient->id())['grant_limit_atoms'] === NULL, 'Unlimited single gifts retain NULL');
}
$draft = $setup->prepare($input, 'gift-stale', time());
$setup->setEnabled(FALSE, $gift['budget_pool_id']);
denied(fn() => $setup->save($draft), 'gift_configuration_changed');
$setup->setEnabled(TRUE, $gift['budget_pool_id']);
$pool = $setup->current()['pool'];
$fixed = array_replace($draft['input'], ['mode' => 'fixed', 'cap_atoms' => NULL]);
denied(fn() => $publication->normalize('pool', $fixed), 'invalid_amount');
$budgeted = array_diff_key($draft['input'], array_flip(['accounting_mode', 'scope']));
$budgeted['bounds'] = []; $budgeted['cap_atoms'] = NULL;
denied(fn() => $publication->normalize('pool', $budgeted), 'invalid_amount');
$before = snapshot();
$db->update('credit_reward_pool')->fields(['issued_atoms' => PHP_INT_MAX - 1])->condition('id', $pool['id'])->execute();
$overflow = snapshot();
denied(fn() => $rewards->gift($gift, 'gift-overflow'), 'reward_budget_exhausted');
check(snapshot() === $overflow, 'Unlimited pool overflow produces no partial ledger writes');
$db->update('credit_reward_pool')->fields(['issued_atoms' => $pool['issued_atoms']])->condition('id', $pool['id'])->execute();
check(\Drupal::service('xinshi_credits.audit')->inspect((string) $recipient->id())['status'] === 'complete', 'All optional-limit gifts reconstruct from the shared ledger');

$setup->save($setup->prepare(array_replace($input, ['grant_limit_atoms' => NULL]), 'gift-race', time()));
$tasks = [];
foreach ([0, 1] as $index) {
  $target = User::create(['name' => 'gift_race_' . $index, 'status' => 1]); $target->save();
  $tasks[] = array_replace($gift, ['beneficiary_user_id' => (string) $target->id(), 'amount_atoms' => '1500',
    'budget_pool_id' => $setup->current()['pool']['id']]);
}
file_put_contents($fixture . '/gift-race.json', json_encode($tasks));
$processes = [];
try {
  foreach ([0, 1] as $index) $processes[] = proc_open([PHP_BINARY, '-d', 'extension=pdo_sqlite', '-d', 'extension=pdo_mysql', __FILE__, 'worker', (string) $index],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', $fixture . '/gift-race.log', 'a'], 2 => ['file', $fixture . '/gift-race.log', 'a']], $pipes);
  $deadline = microtime(TRUE) + 15;
  while (!is_file($fixture . '/gift-race-0.ready') || !is_file($fixture . '/gift-race-1.ready')) {
    if (microtime(TRUE) > $deadline) throw new RuntimeException('Gift worker bootstrap timeout');
    usleep(10000);
  }
  file_put_contents($fixture . '/gift-race.go', 'go');
  foreach ($processes as $process) {
    $status = proc_close($process);
    if ($status !== 0) echo file_get_contents($fixture . '/gift-race.log');
    check($status === 0, 'Concurrent gift worker completes');
  }
  $processes = [];
  $outcomes = [file_get_contents($fixture . '/gift-race-0.result'), file_get_contents($fixture . '/gift-race-1.result')]; sort($outcomes);
  check($outcomes === ['granted', 'reward_budget_exhausted'], 'Concurrent unlimited single gifts cannot exceed a finite shared total');
  check((string) $setup->current()['pool']['issued_atoms'] === '1500', 'Only the winning gift consumes allocation');
}
finally { foreach ($processes as $process) if (is_resource($process)) { proc_terminate($process); proc_close($process); } }

$giftConfig->getEditable('xinshi_credits.settings')->setData($savedCredits)->save();
$giftConfig->getEditable('xinshi_rewards.settings')->setData($savedRewards)->save();
echo 'Gift settings checks: ' . $checks . PHP_EOL;
