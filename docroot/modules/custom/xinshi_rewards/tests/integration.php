<?php

declare(strict_types=1);

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_credits\CreditBudgets;
use Drupal\xinshi_credits\CreditLedger;
use Drupal\xinshi_credits\CreditStore;
use Drupal\xinshi_rewards\Rewards;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
require __DIR__ . '/../../xinshi_credits/tests/publication-fixtures.php';

\Drupal::service('module_installer')->install(['xinshi_rewards']);
check(\Drupal::moduleHandler()->moduleExists('xinshi_rewards'), 'Rewards installs after credits-only coverage');
$rewardConfigFactory = \Drupal::configFactory();
$rewardConfigFactory->getEditable('xinshi_credits.settings')->set('site_id', 'reward-fixture')->set('grants_enabled', TRUE)->save();
$principal = \Drupal::currentUser();
$principal->setAccount(User::load(1));
$publication = \Drupal::service('xinshi_credits.publication');
$now = time();
$buckets = [];
foreach (['chat', 'image'] as $category) {
  $budget = budgetInput('rewards-' . $category, $category, '10000000', '0');
  $budget['period_end'] = $now + 7 * 86400;
  $buckets[] = $publication->publish('budget', $budget)['id'];
}
$pools = [];
foreach (['G11', 'G12'] as $rule) {
  $input = allocationInput('reward-' . $rule, $buckets, '100000', '1000');
  $input['rule_id'] = $rule;
  $input['period_start'] = $now - 30;
  $input['period_end'] = $now + 6 * 86400;
  $input['expires_at'] = $now + 5 * 86400;
  $input['mode'] = $rule === 'G11' ? 'fixed' : 'variable';
  $input['source_kind'] = $rule === 'G11' ? 'promotion' : 'admin_gift';
  $pools[$rule] = $publication->publish('pool', $input)['id'];
}
Role::create(['id' => 'reward_eligible', 'label' => 'Synthetic reward eligibility',
  'permissions' => ['view own credits', 'receive daily credits']])->save();
Role::create(['id' => 'reward_admin', 'label' => 'Synthetic reward administrator',
  'permissions' => ['view user credits', 'grant user credits', 'administer users', 'access user profiles']])->save();
$users = [];
foreach (['eligible' => ['reward_eligible'], 'ineligible' => ['credit_reader'], 'admin' => ['reward_admin']] as $name => $roles) {
  $user = User::create(['name' => 'reward_' . $name, 'status' => 1, 'roles' => $roles, 'pass' => 'synthetic-pass']);
  $user->save(); $users[$name] = $user;
}
$clock = new FixtureClock($now);
$context = \Drupal::service('xinshi_credits.context');
$ledger = new CreditLedger($db, $context, \Drupal::service('uuid'), \Drupal::entityTypeManager(),
  new CreditBudgets($db, $context, $clock), new CreditStore($db, $context, $clock, \Drupal::service('uuid')));
$rewards = new Rewards($db, $rewardConfigFactory, $context, $ledger, $clock, $principal, \Drupal::entityTypeManager(), \Drupal::service('flood'));
$rewardSettings = $rewardConfigFactory->getEditable('xinshi_rewards.settings');
$principal->setAccount($users['eligible']);
check($rewards->status()['status'] === 'paused', 'Installation defaults do not grant rewards');
$rewardSettings->set('daily_enabled', TRUE)->save();
denied(fn() => $rewards->daily('no-zone'), 'rewards_not_configured');
$rewardSettings->set('business_timezone', 'Asia/Shanghai')->save();
$status = $rewards->status();
check($status['status'] === 'eligible' && $status['amount_atoms'] === '1000', 'Daily reads published amount and explicit calendar');
check(!$db->select('credit_reward_claim', 'c')->countQuery()->execute()->fetchField(), 'Reading conditions creates no claim');
$first = $rewards->daily('device-a');
check($first['status'] === 'granted', 'First authenticated activity commits a daily grant');
check($rewards->daily('device-a') === $first, 'Original request replays exactly');
$other = $rewards->daily('device-b');
check($other['status'] === 'already_granted' && $other['receipt'] === $first['receipt'], 'Other device receives same daily transaction');
check($rewards->status()['status'] === 'already_granted', 'Reward state reflects authoritative daily qualification');
check((int) $db->select('credit_reward_claim', 'c')->countQuery()->execute()->fetchField() === 1, 'Only one claim exists for the day');
// A same-day rule change must not enter the deduplication identity.
$db->update('credit_reward_pool')->fields(['rule_version' => 'synthetic-v2'])->condition('id', $pools['G11'])->execute();
check($rewards->daily('new-rule')['receipt'] === $first['receipt'], 'Same-day version replacement cannot grant again');
$rewardSettings->set('daily_enabled', FALSE)->save();
check($rewards->daily('device-a') === $first, 'Paused admission preserves accepted request replay');
$rewardSettings->set('daily_enabled', TRUE)->set('business_timezone', 'UTC')->save();
denied(fn() => $rewards->daily('calendar-change'), 'reward_calendar_changed');
check($rewards->daily('device-a') === $first, 'Calendar mismatch does not erase accepted requests');
$rewardSettings->set('business_timezone', 'Asia/Shanghai')->save();
$clock->now += 86400;
check($rewards->daily('device-a') === $first, 'Cross-midnight retry preserves original business date');
$second = $rewards->daily('tomorrow');
check($second['receipt']['journal_id'] !== $first['receipt']['journal_id'], 'Next active business date may grant once');
$principal->setAccount($users['ineligible']);
check($rewards->daily('no-eligibility')['status'] === 'not_eligible', 'Active users do not receive eligibility automatically');
$principal->setAccount($users['admin']);
$gift = ['beneficiary_user_id' => (string) $users['eligible']->id(), 'amount_atoms' => '125',
  'budget_pool_id' => $pools['G12'], 'scope' => ['image', 'chat'], 'expires_at' => $now + 3 * 86400,
  'public_reason' => 'Synthetic administrator gift', 'internal_note' => 'PRIVATE REWARD NOTE'];
$rewardSettings->set('admin_gifts_enabled', FALSE)->save();
denied(fn() => $rewards->gift($gift, 'admin-request'), 'admin_gifts_disabled');
$rewardSettings->set('admin_gifts_enabled', TRUE)->save();
$given = $rewards->gift($gift, 'admin-request');
check($given['receipt']['amount_atoms'] === '125', 'Authorized administrator gives an exact variable amount');
check($rewards->gift(array_replace($gift, ['scope' => ['chat', 'image']]), 'admin-request') === $given, 'Normalized gift replays exactly');
denied(fn() => $rewards->gift(array_replace($gift, ['amount_atoms' => '126']), 'admin-request'), 'idempotency_conflict');
foreach ([['amount_atoms' => '1001'], ['scope' => ['chat']], ['expires_at' => $now + 6 * 86400]] as $change) {
  denied(fn() => $rewards->gift(array_replace($gift, $change), 'invalid-' . array_key_first($change)), 'invalid_grant_policy');
}
denied(fn() => $rewards->gift($gift + ['actor_user_id' => '1'], 'forged-actor'), 'invalid_fields');
$rewardSettings->set('admin_gifts_enabled', FALSE)->save();
check($rewards->gift($gift, 'admin-request') === $given, 'Paused administrator gifts retain receipts');
$principal->setAccount($users['eligible']);
denied(fn() => $rewards->gift($gift, 'unauthorized'), 'permission_denied');
$query = \Drupal::service('xinshi_credits.query');
$events = $query->notifications()['rows'];
check(count($events) === 3, 'Each committed grant has one durable notification');
check(!str_contains(json_encode($events), 'PRIVATE'), 'Notification does not expose internal notes');
$snapshot = snapshot();
$event = $events[0]['event_id'];
check($query->acknowledge($event) === $query->acknowledge($event), 'Notification acknowledgement is idempotent');
check(count($query->notifications()['rows']) === 2, 'Acknowledged event is removed from pending feed');
check(snapshot()['credit_journal'] === $snapshot['credit_journal'], 'Notification acknowledgement does not write ledger entries');
$principal->setAccount($users['ineligible']);
denied(fn() => $query->acknowledge($event), 'notification_not_found');
denied(fn() => $query->account((string) $users['eligible']->id()), 'permission_denied');
$principal->setAccount($users['admin']);
check($query->account((string) $users['eligible']->id())['available_atoms'] === '2125', 'Managed-user account reads authoritative balance');
$history = $query->transactions(['from' => gmdate('c', $now - 60), 'to' => gmdate('c', $now + 2 * 86400)], (string) $users['eligible']->id());
check(count($history['rows']) === 3 && !str_contains(json_encode($history), 'PRIVATE'), 'Administrator read does not grant internal audit access');
// The extension callback runs after grant/outbox insertion, before the root commit.
$snapshot = snapshot();
$command = command($pools['G12'], 'G12', 'callback-failure', (string) $users['eligible']->id(), '1');
$command['expires_at'] = $now + 3 * 86400;
try {
  $ledger->grant($command, 'callback-failure', static function (): void { throw new RuntimeException('Synthetic late failure'); });
  throw new RuntimeException('Expected callback failure');
}
catch (RuntimeException $error) { check($error->getMessage() === 'Synthetic late failure', 'Late qualified-record failure propagates'); }
check(snapshot() === $snapshot, 'Late callback failure rolls back balance, budgets, grant, journal and notification');
check(count(\Drupal::service('xinshi_rewards.uninstall')->validate('xinshi_rewards')) === 1, 'Committed qualifications block destructive module uninstall');
$rollbackUser = User::create(['name' => 'reward-rollback', 'status' => 1, 'roles' => ['reward_eligible']]);
$rollbackUser->save();
$principal->setAccount($rollbackUser);
$beforeFailure = snapshot();
$rewardBefore = [];
foreach (['credit_reward_claim', 'credit_reward_request', 'credit_reward_calendar'] as $table) {
  $rewardBefore[$table] = $db->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
}
$trigger = $db->driver() === 'mysql'
  ? "CREATE TRIGGER reject_reward_request BEFORE INSERT ON {credit_reward_request} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic request failure'"
  : "CREATE TRIGGER reject_reward_request BEFORE INSERT ON {credit_reward_request} BEGIN SELECT RAISE(ABORT, 'synthetic request failure'); END";
$db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
$failed = FALSE;
try { $rewards->daily('late-record-failure'); }
catch (\Throwable) { $failed = TRUE; }
finally { $db->query('DROP TRIGGER reject_reward_request'); }
check($failed && snapshot() === $beforeFailure, 'Rejected request mapping rolls back the full financial transaction');
foreach ($rewardBefore as $table => $rows) {
  check($db->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC) === $rows,
    'Rejected request mapping rolls back: ' . $table);
}
$users['eligible']->block()->save();
$principal->setAccount($users['eligible']);
denied(fn() => $rewards->daily('device-a'), 'permission_denied');
$users['eligible']->activate()->save();
$principal->setAccount($users['eligible']);
$clock->now += 86400;
$db->update('credit_reward_pool')->fields(['cap_atoms' => 2000])->condition('id', $pools['G11'])->execute();
$before = snapshot();
check($rewards->daily('exhausted')['status'] === 'budget_closed', 'Exhausted daily allocation gives an explicit outcome');
check(snapshot() === $before, 'Budget rejection leaves no financial or notification write');
// The fixture kernel disables container dumping; discard the earlier HTTP kernel's definition.
\Drupal::service('kernel')->invalidateContainer();
echo 'Rewards checks: ' . $checks . PHP_EOL;
