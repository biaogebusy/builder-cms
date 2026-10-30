<?php

declare(strict_types=1);

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\xinshi_credits\CreditException;
use Drupal\xinshi_credits\CreditValue;

// This fixture is only included after installation in the guarded disposable runner.
if (getenv('XINSHI_CREDITS_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv')
  || realpath(DRUPAL_ROOT) !== '/app/docroot') throw new RuntimeException('Use the isolated runner.');
$checks = 0;
function check(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
  $GLOBALS['checks']++;
  echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $run, string $code): void {
  try { $run(); }
  catch (CreditException $error) {
    check($error->reason === $code, 'Denied: ' . $code . ' (got ' . $error->reason . ')');
    return;
  }
  throw new RuntimeException('Expected denial: ' . $code);
}
require __DIR__ . '/fixtures.php';
$db = \Drupal::database();
$ledger = \Drupal::service('xinshi_credits.ledger');
$query = \Drupal::service('xinshi_credits.query');
$creditsConfig = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
check($creditsConfig->get('site_id') === NULL, 'No default site, rewards, or budget allocation');
check($creditsConfig->get('grants_enabled') === FALSE, 'New grant admission defaults to disabled');
foreach (['xinshi_ai', 'xinshi_ai_usage', 'commerce', 'message', 'simple_oauth'] as $module) {
  check(!\Drupal::moduleHandler()->moduleExists($module), 'Ledger installs without ' . $module);
}
check(\Drupal::service('xinshi_credits.uninstall')->validate('xinshi_credits') === [], 'Empty ledger permits uninstall');
Role::create(['id' => 'credit_reader', 'label' => 'Credit reader', 'permissions' => ['view own credits']])->save();
$users = [];
foreach (['alice', 'bob', 'no_grant'] as $name) {
  $user = User::create(['name' => $name, 'pass' => 'credits-test-password', 'status' => 1,
    'roles' => $name === 'no_grant' ? [] : ['credit_reader']]);
  $user->save(); $users[$name] = $user;
}
\Drupal::currentUser()->setAccount($users['alice']);
denied(fn() => $query->account(), 'credits_not_configured');
$creditsConfig->set('site_id', 'fixture')->save();
check($query->account()['status'] === 'not_initialized', 'Read does not initialize a balance');
check(count(snapshot()['credit_account']) === 0, 'Uninitialized read has no ledger side effects');
foreach (['-1', '01', '1.1', '1e3', '', '9223372036854775808', 1000, 1.0, TRUE] as $bad) {
  denied(fn() => CreditValue::amount($bad), 'invalid_amount');
}
check(CreditValue::amount('9007199254740993') === 9007199254740993, 'Amounts preserve values beyond JS precision');
check(CreditValue::cost(1, 1) === 1 && CreditValue::cost(1001, 5000) === 5005, 'Cost rounds upward at atom precision');
denied(fn() => CreditValue::add(PHP_INT_MAX, 1), 'amount_overflow');
denied(fn() => CreditValue::cost(PHP_INT_MAX, PHP_INT_MAX), 'amount_overflow');
$p = pool('G12');
$first = command($p, 'G12', 'event-1', (string) $users['alice']->id());
denied(fn() => $ledger->grant($first, 'key-1'), 'credits_grants_disabled');
$creditsConfig->set('grants_enabled', TRUE)->save();
$receipt = $ledger->grant($first, 'key-1');
$after = snapshot();
check($receipt['amount_atoms'] === '1000' && $query->account()['available_atoms'] === '1000', 'Grant commits exact available balance');
check(count($after['credit_posting']) === 2 && array_sum(array_column($after['credit_posting'], 'delta_atoms')) === 0, 'Grant has balanced postings');
check(count($after['credit_notification_outbox']) === 1, 'Grant records durable notification intent');
check(array_column($after['credit_budget_bucket'], 'committed_cost_micros') == [5000, 5000], 'Universal grant commits both cost categories');
check($ledger->grant($first, 'key-1') === $receipt && snapshot() === $after, 'Identical retry has no new writes');
$creditsConfig->set('grants_enabled', FALSE)->save();
check($ledger->grant($first, 'key-1') === $receipt, 'Pause still resolves committed idempotent retries');
denied(fn() => $ledger->grant(array_replace($first, ['source_ref' => 'paused']), 'paused'), 'credits_grants_disabled');
check($query->account()['available_atoms'] === '1000' && snapshot() === $after, 'Pause retains readable balances and all ledger records');
$creditsConfig->set('grants_enabled', TRUE)->save();
denied(fn() => $ledger->grant(array_replace($first, ['amount_atoms' => '1001']), 'key-1'), 'idempotency_conflict');
denied(fn() => $ledger->grant($first, 'alias-key'), 'idempotency_conflict');
denied(fn() => $ledger->grant(array_replace($first, ['rule_version' => 'v2']), 'other-key'), 'idempotency_conflict');
check(snapshot() === $after, 'Conflicts cannot duplicate origin or consume budget');
denied(fn() => $ledger->grant(array_replace($first, ['source_ref' => 'bad-scope', 'scope' => ['video']]), 'bad-scope'), 'invalid_budget_scope');
denied(fn() => $ledger->grant(array_replace($first, ['source_ref' => 'bad-expiry', 'expires_at' => time() - 1]), 'bad-expiry'), 'invalid_expiry');
check(snapshot() === $after, 'Unsupported scope and expired grants have no writes');
$small = pool('small', '1000');
$a = command($small, 'small', 'small-1', (string) $users['alice']->id());
$ledger->grant($a, 'small-1');
$before = snapshot();
denied(fn() => $ledger->grant(array_replace($a, ['source_ref' => 'small-2']), 'small-2'), 'reward_budget_exhausted');
check(snapshot() === $before, 'Pool exhaustion rolls back already updated cost buckets');
$tight = pool('tight', '1000000', '4999');
$before = snapshot();
denied(fn() => $ledger->grant(command($tight, 'tight', 'cost-1', (string) $users['alice']->id()), 'cost-1'), 'reward_budget_exhausted');
check(snapshot() === $before, 'Cost ceiling blocks a grant even with available reward points');
$trigger = $db->driver() === 'mysql'
  ? "CREATE TRIGGER reject_credit_outbox BEFORE INSERT ON {credit_notification_outbox} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic late failure'"
  : "CREATE TRIGGER reject_credit_outbox BEFORE INSERT ON {credit_notification_outbox} BEGIN SELECT RAISE(ABORT, 'synthetic late failure'); END";
$db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
$before = snapshot(); $failed = FALSE;
try { $ledger->grant(command($p, 'G12', 'late-failure', (string) $users['bob']->id()), 'late-failure'); }
catch (Throwable) { $failed = TRUE; }
$db->query('DROP TRIGGER reject_credit_outbox');
check($failed && snapshot() === $before, 'Late outbox failure rolls back account, budgets, grant, journal and postings');
$second = $ledger->grant(array_replace($first, ['source_ref' => 'event-2']), 'key-2');
check($ledger->grant($first, 'key-1') === $receipt && $query->account()['available_atoms'] === '3000', 'Retry receipt stays historical after later grants');
check(count(\Drupal::service('xinshi_credits.uninstall')->validate('xinshi_credits')) === 1, 'Committed ledger blocks routine uninstall');
$filter = ['from' => gmdate('c', time() - 60), 'to' => gmdate('c', time() + 7200), 'limit' => '1'];
$page = $query->transactions($filter);
check(count($page['rows']) === 1 && $page['next_cursor'] !== NULL, 'Bounded first history page');
$third = $ledger->grant(array_replace($first, ['source_ref' => 'event-3']), 'key-3');
$seen = [$page['rows'][0]['journal_id']]; $cursor = $page['next_cursor'];
do {
  $next = $query->transactions($filter + ['cursor' => $cursor]);
  $seen = [...$seen, ...array_column($next['rows'], 'journal_id')]; $cursor = $next['next_cursor'];
} while ($cursor !== NULL);
check(count($seen) === 3 && count(array_unique($seen)) === 3 && !in_array($third['journal_id'], $seen), 'Paging watermark excludes later grants without repeats or skips');
check(!str_contains(json_encode($query->transactions($filter)), 'PRIVATE AUDIT')
  && !array_key_exists('source_ref', $page['rows'][0]), 'Public history omits private note and raw source reference');
denied(fn() => $query->transactions($filter + ['cursor' => $page['next_cursor'] . 'x']), 'invalid_cursor');
denied(fn() => $query->transactions(array_replace($filter, ['limit' => '2', 'cursor' => $page['next_cursor']])), 'invalid_cursor');
denied(fn() => $query->transactions(array_replace($filter, ['from' => '2026-02-30T00:00:00Z'])), 'invalid_period');
denied(fn() => $query->transactions(array_replace($filter, ['limit' => '101'])), 'invalid_limit');
denied(fn() => $query->transactions($filter + ['owner_id' => '1']), 'invalid_fields');
denied(fn() => $query->transactions(['from' => '2020-01-01T00:00:00Z', 'to' => '2026-01-01T00:00:00Z']), 'invalid_period');
\Drupal::currentUser()->setAccount($users['bob']);
check($query->account()['status'] === 'not_initialized', 'Other user cannot see owner balance');
denied(fn() => $query->transactions($filter + ['cursor' => $page['next_cursor']]), 'invalid_cursor');
\Drupal::currentUser()->setAccount($users['no_grant']);
denied(fn() => $query->account(), 'permission_denied');
\Drupal::currentUser()->setAccount($users['alice']);
$db->update('credit_grant')->fields(['expires_at' => time() - 1])->condition('id', $receipt['grant_id'])->execute();
$expired = $query->account();
check($expired['status'] === 'expiry_pending' && !isset($expired['available_atoms']), 'Expired unprocessed funds are never reported as spendable');
$db->update('credit_grant')->fields(['expires_at' => $first['expires_at']])->condition('id', $receipt['grant_id'])->execute();
$creditsConfig->set('site_id', 'another-site')->save();
check($query->account()['status'] === 'not_initialized', 'Site identity isolates account reads');
$creditsConfig->set('site_id', 'fixture')->save();
$racePool = pool('race', '1000', '5000');
file_put_contents('/tmp/credits-test/fixtures.json', json_encode(['alice' => (string) $users['alice']->id(),
  'first_grant' => $first, 'first_receipt' => $receipt,
  'race' => command($racePool, 'race', 'race-origin', (string) $users['alice']->id()), 'filter' => $filter]));
\Drupal::service('router.builder')->rebuild();
echo 'Ledger checks: ' . $checks . PHP_EOL;
