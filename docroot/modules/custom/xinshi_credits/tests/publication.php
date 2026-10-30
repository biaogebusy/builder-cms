<?php

declare(strict_types=1);

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_credits\CreditPublication;
use Drupal\xinshi_credits\CreditValue;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/publication-fixtures.php';
require_once '/app/docroot/modules/custom/xinshi_credits/xinshi_credits.install';

$creditsConfig = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$creditsConfig->set('site_id', 'publication')->set('grants_enabled', FALSE)->set('spending_enabled', FALSE)->save();
$publisher = \Drupal::service('xinshi_credits.publication');
$current = \Drupal::currentUser();
denied(fn() => $publisher->publish('budget', budgetInput('anon', 'chat')), 'permission_denied');
Role::create(['id' => 'publisher', 'label' => 'Publisher', 'permissions' => ['administer credit budgets']])->save();
$admin = User::create(['name' => 'publisher', 'status' => 1, 'roles' => ['publisher']]); $admin->save();
$current->setAccount($admin);
$budget = budgetInput('chat-1', 'chat');
$receipt = $publisher->publish('budget', $budget);
check($receipt === $publisher->publish('budget', $budget), 'Repeated publication returns the same identity');
$stored = $db->select('credit_budget_bucket', 'b')->fields('b')->condition('id', $receipt['id'])->execute()->fetchAssoc();
check($stored['created_by'] === (string) $admin->id(), 'Publication actor comes from current authentication');
check(count(\Drupal::service('xinshi_credits.uninstall')->validate('xinshi_credits')) === 1, 'Uninstall retains published limits');
$before = snapshot();
denied(fn() => $publisher->publish('budget', array_replace($budget, ['cap_micros' => '10001'])), 'idempotency_conflict');
denied(fn() => $publisher->publish('budget', array_replace($budget, ['publication_ref' => 'overlap', 'currency' => 'USD'])), 'publication_period_overlap');
check(snapshot() === $before, 'Rejected publications change no table or guard');
$next = array_replace($budget, ['publication_ref' => 'chat-next', 'period_start' => $budget['period_end'], 'period_end' => $budget['period_end'] + 86400]);
check($publisher->publish('budget', $next)['id'] !== $receipt['id'], 'Adjacent half-open periods can publish');
$image = $publisher->publish('budget', budgetInput('image-1', 'image'));
$poolInput = allocationInput('gift-1', [$receipt['id'], $image['id']]);
$before = snapshot();
$pool = $publisher->publish('pool', $poolInput);
foreach (['credit_account', 'credit_grant', 'credit_journal', 'credit_posting', 'credit_notification_outbox'] as $table) {
  check(snapshot()[$table] === $before[$table], 'Publication does not issue credits: ' . $table);
}
check($creditsConfig->get('grants_enabled') === FALSE && $creditsConfig->get('spending_enabled') === FALSE, 'Publication does not enable credits');
$reversed = $poolInput; $reversed['bounds'] = array_reverse($reversed['bounds']);
check($publisher->publish('pool', $reversed) === $pool, 'Equivalent category order replays the same publication');
denied(fn() => $publisher->publish('pool', array_replace($poolInput, ['publication_ref' => 'same-rule', 'rule_version' => 'v2'])), 'publication_period_overlap');
$before = snapshot();
$oversized = allocationInput('too-large', [$receipt['id'], $image['id']], '8000');
denied(fn() => $publisher->publish('pool', $oversized), 'reward_budget_exhausted');
check(snapshot() === $before, 'Oversized allocation rolls back all category plans');
$tiny = $publisher->publish('budget', budgetInput('rounding', 'tiny', '2500', '500'));
$variable = allocationInput('variable', [$tiny['id']], '2000', '1000', '1'); $variable['mode'] = 'variable';
$varPool = $publisher->publish('pool', $variable);
$planned = $db->select('credit_pool_allocation', 'a')->fields('a', ['planned_cost_micros'])->condition('pool_id', $varPool['id'])->execute()->fetchField();
check((int) $planned === 2000, 'Variable plan covers 2000 one-atom grants with upward rounding');
denied(fn() => $publisher->publish('pool', allocationInput('rounding-overflow', [$tiny['id']], '1', '1', '1')), 'reward_budget_exhausted');
foreach ([['cap_atoms' => '1500'], ['expires_at' => $poolInput['period_end'] + 1], ['expires_at' => $poolInput['period_start']],
  ['source_kind' => 'purchase'], ['grant_limit_atoms' => '3000']] as $change) {
  denied(fn() => $publisher->normalize('pool', array_replace($poolInput, $change)), 'invalid_grant_policy');
}
denied(fn() => $publisher->normalize('budget', $budget + ['actor_user_id' => '1']), 'invalid_fields');
denied(fn() => $publisher->normalize('budget', array_replace($budget, ['cap_micros' => 10000])), 'invalid_amount');
denied(fn() => $publisher->normalize('budget', array_replace($budget, ['execution_headroom_micros' => '10001'])), 'invalid_budget_bound');
denied(fn() => $publisher->normalize('pool', array_replace($poolInput, ['bounds' => [$poolInput['bounds'][0], $poolInput['bounds'][0]]])), 'invalid_budget_bound');
check(CreditValue::decimal('12.001', 3) === '12001' && CreditValue::decimal('0.000001', 6) === '1', 'Decimal form amounts remain exact');
check(CreditValue::display('12001000', 6) === '12.001' && CreditValue::display('0', 3) === '0', 'Display preserves whole and fractional amounts');
foreach (['1e3', '1.0001', '-1', '01', ' 1', '9223372036854775.808'] as $amount) denied(fn() => CreditValue::decimal($amount, 3), 'invalid_amount');
denied(fn() => CreditValue::multiply(PHP_INT_MAX, 2), 'amount_overflow');
$blocked = User::create(['name' => 'blocked-publisher', 'status' => 0, 'roles' => ['publisher']]); $blocked->save();
$current->setAccount($blocked);
denied(fn() => $publisher->catalog('budget'), 'permission_denied');
$current->setAccount(user_load_by_name('alice'));
denied(fn() => $publisher->publish('budget', $budget), 'permission_denied');
$current->setAccount($admin);

$creditsConfig->set('grants_enabled', TRUE)->save();
$user = User::create(['name' => 'publication-beneficiary', 'status' => 1]); $user->save();
$gift = command($pool['id'], 'gift-1', 'pub-gift', (string) $user->id());
$gift['expires_at'] = $poolInput['expires_at'];
$ledger = \Drupal::service('xinshi_credits.ledger');
foreach ([['amount_atoms' => '500'], ['amount_atoms' => '1001'], ['source_kind' => 'promotion'],
  ['scope' => ['chat']], ['expires_at' => $poolInput['expires_at'] + 1]] as $change) {
  denied(fn() => $ledger->grant(array_replace($gift, $change), 'pub-invalid'), 'invalid_grant_policy');
}
$giftReceipt = $ledger->grant($gift, 'pub-gift');
check($giftReceipt['amount_atoms'] === '1000', 'Published fixed policy permits the exact grant');
$creditsConfig->set('spending_enabled', TRUE)->save();
$life = \Drupal::service('xinshi_credits.lifecycle');
$common = ['beneficiary_user_id' => (string) $user->id(), 'actor_user_id' => '1', 'public_reason' => 'Synthetic execution'];
$hold = $common + ['operation_id' => 'publication-operation', 'quote_id' => 'q1', 'authorization_version' => 'v1',
  'quote_expires_at' => time() + 600, 'category' => 'chat', 'amount_atoms' => '1000',
  'cost_bucket_id' => $receipt['id'], 'cost_limit_micros' => '7000', 'cost_currency' => 'CNY'];
$life->reserve($hold, 'publication-hold');
$newPool = allocationInput('after-execution', [$receipt['id']]);
denied(fn() => $publisher->publish('pool', $newPool), 'reward_budget_exhausted');
$life->release($common + ['operation_id' => 'publication-operation', 'outcome' => 'not_started', 'evidence_ref' => 'Synthetic no-start proof'], 'publication-release');
check(isset($publisher->publish('pool', $newPool)['id']), 'Verified release restores publication space without changing existing plans');
$trigger = $db->driver() === 'mysql'
  ? "CREATE TRIGGER reject_credit_pool BEFORE INSERT ON {credit_reward_pool} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic late failure'"
  : "CREATE TRIGGER reject_credit_pool BEFORE INSERT ON {credit_reward_pool} BEGIN SELECT RAISE(ABORT, 'synthetic late failure'); END";
$db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
$before = snapshot(); $failed = FALSE;
try { $publisher->publish('pool', allocationInput('late-failure', [$receipt['id'], $image['id']])); }
catch (\Drupal\Core\Database\DatabaseException) { $failed = TRUE; }
$db->query('DROP TRIGGER reject_credit_pool');
check($failed && snapshot() === $before, 'Late publication failure rolls back every allocation and guard change');
$variableGift = command($varPool['id'], 'variable', 'variable-gift', (string) $user->id(), '123');
$variableGift['scope'] = ['tiny']; $variableGift['expires_at'] = $variable['expires_at'];
denied(fn() => $ledger->grant(array_replace($variableGift, ['amount_atoms' => '1001']), 'variable-too-large'), 'invalid_grant_policy');
check($ledger->grant($variableGift, 'variable-valid')['amount_atoms'] === '123', 'Variable policy permits an exact amount below the published maximum');
$creditsConfig->set('spending_enabled', FALSE)->save();
$creditsConfig->set('grants_enabled', FALSE)->save();
check($ledger->grant($gift, 'pub-gift') === $giftReceipt, 'Existing grant receipts replay while paused');
$late = new CreditPublication($db, \Drupal::service('xinshi_credits.context'), new FixtureClock($budget['period_end'] + 1),
  \Drupal::service('uuid'), $current, \Drupal::entityTypeManager());
check($late->publish('budget', $budget) === $receipt, 'Expired publication still replays its immutable identity');
denied(fn() => $late->publish('budget', array_replace($budget, ['publication_ref' => 'past', 'category' => 'past'])), 'invalid_period');

// Recreate CR2b storage omissions in this disposable site, preserving every old field.
$metadata = ['credit_budget_bucket' => ['publication_ref', 'publication_hash', 'execution_headroom_micros'],
  'credit_reward_pool' => ['publication_ref', 'publication_hash', 'grant_policy_json', 'cost_evidence_json']];
foreach ($metadata as $table => $fields) {
  $db->schema()->dropUniqueKey($table, 'publication');
  foreach ($fields as $field) $db->schema()->dropField($table, $field);
}
foreach (['credit_pool_allocation', 'credit_publication_guard'] as $table) $db->schema()->dropTable($table);
$old = [];
foreach (['credit_budget_bucket', 'credit_reward_pool', 'credit_journal'] as $table) $old[$table] = $db->select($table, 't')->fields('t')->execute()->fetchAll(PDO::FETCH_ASSOC);
$creditsConfig->set('spending_enabled', TRUE)->save();
$refused = FALSE;
try { xinshi_credits_update_11002(); } catch (\Drupal\Core\Utility\UpdateException) { $refused = TRUE; }
check($refused && !$db->schema()->tableExists('credit_publication_guard'), 'Publication upgrade refuses enabled consumption before changes');
$creditsConfig->set('spending_enabled', FALSE)->save();
xinshi_credits_update_11002();
foreach ($old as $table => $rows) check($db->select($table, 't')->fields('t', array_keys($rows[0]))->execute()->fetchAll(PDO::FETCH_ASSOC) === $rows, 'Publication upgrade preserves CR2b data: ' . $table);
check($publisher->catalog('budget')['rows'] === [], 'Upgrade does not adopt legacy budgets as approved publications');
$state = snapshot(); xinshi_credits_update_11002();
check(snapshot() === $state, 'Publication upgrade is repeatable');
$creditsConfig->set('site_id', 'fixture')->save();
echo 'Publication checks: ' . $checks . PHP_EOL;
