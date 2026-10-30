<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';

$clock = new FixtureClock(time());
$initialTime = $clock->now;
$context = \Drupal::service('xinshi_credits.context'); $uuid = \Drupal::service('uuid');
$entities = \Drupal::entityTypeManager();
$budgets = new \Drupal\xinshi_credits\CreditBudgets($db, $context, $clock);
$store = new \Drupal\xinshi_credits\CreditStore($db, $context, $clock, $uuid);
$ledger = new \Drupal\xinshi_credits\CreditLedger($db, $context, $uuid, $entities, $budgets, $store);
$life = new \Drupal\xinshi_credits\CreditLifecycle($db, $context, $store, $budgets, $uuid, $entities);
$query = new \Drupal\xinshi_credits\CreditQuery($db, $context, $clock, \Drupal::service('private_key'), \Drupal::currentUser(), $entities);
$expiry = new \Drupal\xinshi_credits\CreditExpiry($db, $context, $life, $clock, \Drupal::logger('xinshi_credits'));
$audit = \Drupal::service('xinshi_credits.audit');
$user = \Drupal\user\Entity\User::create(['name' => 'lifecycle-user', 'status' => 1, 'roles' => ['credit_reader']]);
$user->save(); $uid = (string) $user->id(); \Drupal::currentUser()->setAccount($user);
$creditsConfig = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$p = pool('lifecycle');
$bounds = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])->condition('id', $p)->execute()->fetchField(), TRUE);
$chatBucket = $bounds['chat']['bucket_id']; $imageBucket = $bounds['image']['bucket_id'];
$gift = command($p, 'lifecycle', 'first', $uid, '600'); $gift['expires_at'] = $clock->now + 100;
$first = $ledger->grant($gift, 'life-gift-1');
$gift['source_ref'] = 'second'; $gift['amount_atoms'] = '800'; $gift['expires_at'] = $clock->now + 200;
$second = $ledger->grant($gift, 'life-gift-2');
$common = ['beneficiary_user_id' => $uid, 'actor_user_id' => '1', 'public_reason' => 'Synthetic lifecycle'];
$hold = $common + ['operation_id' => 'life-op-1', 'quote_id' => 'quote-1', 'authorization_version' => 'v1',
  'quote_expires_at' => $clock->now + 60, 'category' => 'chat', 'amount_atoms' => '1200',
  'cost_bucket_id' => $chatBucket, 'cost_limit_micros' => '2000', 'cost_currency' => 'CNY'];
function budget(string $id): array {
  $row = \Drupal::database()->select('credit_budget_bucket', 'b')->fields('b')->condition('id', $id)->execute()->fetchAssoc();
  return [(int) $row['committed_cost_micros'], (int) $row['reserved_cost_micros'], (int) $row['actual_cost_micros']];
}
check($creditsConfig->get('spending_enabled') === FALSE, 'New spending starts disabled');
$before = snapshot();
denied(fn() => $life->reserve($hold, 'life-hold-1'), 'credits_spending_disabled');
check(snapshot() === $before, 'Disabled reservation changes no ledger or budget table');
$creditsConfig->set('spending_enabled', TRUE)->save();
denied(fn() => $life->reserve(array_replace($hold, ['cost_currency' => 'USD']), 'wrong-hold-currency'), 'cost_currency_mismatch');
$held = $life->reserve($hold, 'life-hold-1');
$savedQuoteExpiry = $db->select('credit_reservation', 'r')->fields('r', ['quote_expires_at'])
  ->condition('id', $held['reservation_id'])->execute()->fetchField();
check((int) $savedQuoteExpiry === $hold['quote_expires_at'], 'Reservation retains the original quote expiry');
check($query->account()['available_atoms'] === '200' && $query->account()['held_atoms'] === '1200', 'Reservation moves available points into held points');
$allocation = $db->select('credit_allocation', 'a')->fields('a')->condition('reservation_id', $held['reservation_id'])
  ->orderBy('position')->execute()->fetchAll(PDO::FETCH_ASSOC);
check(array_column($allocation, 'grant_id') === [$first['grant_id'], $second['grant_id']]
  && array_column($allocation, 'held_atoms') == [600, 600], 'Reservation freezes earliest-expiring grant allocations');
check(budget($chatBucket) === [1000, 8000, 0] && budget($imageBucket) === [1000, 6000, 0], 'Execution and return exposure have separate funded reservations');
$before = snapshot();
check($life->reserve($hold, 'life-hold-1') === $held && snapshot() === $before, 'Reserve retry preserves the original receipt');
denied(fn() => $life->reserve(array_replace($hold, ['operation_id' => 'too-much', 'amount_atoms' => '201']), 'too-much'), 'insufficient_credits');
denied(fn() => $life->reserve(array_replace($hold, ['operation_id' => 'old-quote', 'quote_expires_at' => $clock->now]), 'old-quote'), 'quote_expired');
denied(fn() => $life->reserve(array_replace($hold, ['operation_id' => 'scope', 'category' => 'video']), 'scope'), 'insufficient_credits');
$finish = $common + ['operation_id' => 'life-op-1', 'outcome' => 'completed', 'evidence_ref' => 'synthetic-final-proof',
  'charged_atoms' => '900', 'actual_cost_micros' => '1500', 'cost_status' => 'final', 'cost_currency' => 'CNY'];
denied(fn() => $life->settle(array_replace($finish, ['cost_currency' => 'USD']), 'wrong-currency'), 'cost_currency_mismatch');
denied(fn() => $life->settle(array_replace($finish, ['charged_atoms' => '1201']), 'too-large'), 'authorization_exceeded');
denied(fn() => $life->settle(array_replace($finish, ['outcome' => 'unknown']), 'unknown'), 'execution_unresolved');
denied(fn() => $life->settle(array_replace($finish, ['cost_status' => 'unpriced']), 'unpriced'), 'cost_unresolved');
denied(fn() => $life->release($common + ['operation_id' => 'life-op-1', 'outcome' => 'unknown', 'evidence_ref' => 'timeout'], 'timeout'), 'execution_unresolved');
check(snapshot() === $before, 'Rejected operations preserve held amounts and every cost reserve');
$creditsConfig->set('spending_enabled', FALSE)->save();
denied(fn() => $life->reserve(array_replace($hold, ['operation_id' => 'paused', 'amount_atoms' => '1']), 'paused-hold'), 'credits_spending_disabled');
$settled = $life->settle($finish, 'life-settle-1');
check($settled['charged_atoms'] === '900' && $settled['released_atoms'] === '300'
  && $query->account()['available_atoms'] === '500' && $query->account()['held_atoms'] === '0', 'Paused spending still settles accepted work and releases unused points');
check(budget($chatBucket) === [2500, 4500, 1500] && budget($imageBucket) === [2500, 4500, 0], 'Settlement records actual cost and retains return exposure');
$before = snapshot();
check($life->settle($finish, 'life-settle-1') === $settled && snapshot() === $before, 'Settlement retries do not charge twice');
denied(fn() => $life->release($common + ['operation_id' => 'life-op-1', 'outcome' => 'not_started', 'evidence_ref' => 'contradiction'], 'later-release'), 'idempotency_conflict');
$return = $common + ['operation_id' => 'life-op-1', 'return_ref' => 'correction-1', 'returns' => [
  ['grant_id' => $first['grant_id'], 'amount_atoms' => '100'], ['grant_id' => $second['grant_id'], 'amount_atoms' => '100']]];
$returned = $life->returnCredits($return, 'life-return-1');
check($returned['returned_atoms'] === '200' && $query->account()['available_atoms'] === '700', 'Correction restores only specified original allocations');
check(budget($chatBucket) === [3500, 3500, 1500], 'Returning points never erases supplier actual cost');
$before = snapshot();
check($life->returnCredits($return, 'life-return-1') === $returned && snapshot() === $before, 'Correction retry is idempotent');
denied(fn() => $life->returnCredits(array_replace($return, ['return_ref' => 'excess', 'returns' => [
  ['grant_id' => $first['grant_id'], 'amount_atoms' => '501']]]), 'excess'), 'return_exceeded');
check($audit->inspect($uid)['status'] === 'complete', 'Credits and budgets reconstruct after partial settlement and return');
$clock->now = $initialTime + 110;
check($query->account()['status'] === 'expiry_pending', 'Reads conceal expired funds before cron catches up');
check($audit->inspect($uid)['status'] === 'complete', 'Pending cron is not reported as projection corruption');
$expiry->process();
check($query->account()['available_atoms'] === '600' && budget($chatBucket) === [3000, 1000, 1500], 'Expiry removes available funds and expired return exposure');
$before = snapshot(); $expiry->process();
check(snapshot() === $before, 'Repeated expiry work creates no additional movement');
$lateReturn = array_replace($return, ['return_ref' => 'expired-return', 'returns' => [['grant_id' => $first['grant_id'], 'amount_atoms' => '100']]]);
$late = $life->returnCredits($lateReturn, 'expired-return');
check($late['expired_atoms'] === '100' && $query->account()['available_atoms'] === '600', 'Return to an expired grant preserves its original expiry');
$creditsConfig->set('spending_enabled', TRUE)->save();
$lateHold = array_replace($hold, ['operation_id' => 'life-late', 'amount_atoms' => '400', 'cost_limit_micros' => '1000', 'quote_expires_at' => $clock->now + 60]);
$life->reserve($lateHold, 'life-late-hold');
$clock->now = $initialTime + 210; $expiry->process();
check($query->account()['available_atoms'] === '0' && $query->account()['held_atoms'] === '400', 'Expiry preserves unresolved held points');
check(budget($chatBucket) === [0, 1000, 1500], 'Expiry retains the unresolved execution cost limit');
$lateFinish = array_replace($finish, ['operation_id' => 'life-late', 'charged_atoms' => '250', 'actual_cost_micros' => '400']);
$lateSettled = $life->settle($lateFinish, 'life-late-finish');
check($lateSettled['expired_atoms'] === '150' && $query->account()['held_atoms'] === '0'
  && $query->account()['available_atoms'] === '0', 'Late settlement consumes the authorized hold and expires its unused remainder');
check($audit->inspect($uid)['status'] === 'complete', 'Expired and partially consumed grant projections reconstruct');

$gift['source_ref'] = 'failure'; $gift['amount_atoms'] = '1000'; $gift['expires_at'] = $clock->now + 100;
$third = $ledger->grant($gift, 'life-failure-gift');
$failedHold = array_replace($hold, ['operation_id' => 'failure', 'amount_atoms' => '300', 'cost_limit_micros' => '500', 'quote_expires_at' => $clock->now + 60]);
$life->reserve($failedHold, 'failure-hold');
$user->block()->save();
denied(fn() => $life->reserve(array_replace($failedHold, ['operation_id' => 'blocked']), 'blocked'), 'account_unavailable');
$failure = $life->settle(array_replace($finish, ['operation_id' => 'failure', 'charged_atoms' => '0', 'actual_cost_micros' => '200']), 'failed-final');
check($failure['released_atoms'] === '300' && budget($chatBucket) === [5000, 0, 2100], 'Blocked account can finish a zero-charge failure without losing incurred cost');
$user->activate()->save(); \Drupal::currentUser()->setAccount($user);
check($query->account()['available_atoms'] === '1000', 'Failed operation restores all valid points');

$trigger = $db->driver() === 'mysql'
  ? "CREATE TRIGGER reject_credit_posting BEFORE INSERT ON {credit_posting} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic late failure'"
  : "CREATE TRIGGER reject_credit_posting BEFORE INSERT ON {credit_posting} BEGIN SELECT RAISE(ABORT, 'synthetic late failure'); END";
$db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
$before = snapshot(); $failed = FALSE;
try { $life->reserve(array_replace($failedHold, ['operation_id' => 'late-fault']), 'late-fault'); }
catch (Throwable) { $failed = TRUE; }
$db->query('DROP TRIGGER reject_credit_posting');
check($failed && snapshot() === $before, 'Late posting failure rolls back account, batch, allocations, reservation, budget and journal');
check($audit->inspect($uid)['status'] === 'complete', 'Rollback leaves an exactly reconstructable ledger');
$accountId = $query->account()['account_id'];
$db->update('credit_account')->expression('available_atoms', 'available_atoms + 1')->condition('id', $accountId)->execute();
check(in_array('account_projection_mismatch', $audit->inspect($uid)['issues']), 'Audit detects a corrupted account projection');
check($query->account()['available_atoms'] === '1001', 'Audit does not silently repair financial data');
$db->update('credit_account')->expression('available_atoms', 'available_atoms - 1')->condition('id', $accountId)->execute();
$db->update('credit_budget_bucket')->expression('reserved_cost_micros', 'reserved_cost_micros + 1')->condition('id', $chatBucket)->execute();
check(in_array('budget_projection_mismatch', $audit->inspect($uid)['issues']), 'Audit detects a corrupted budget projection');
$db->update('credit_budget_bucket')->expression('reserved_cost_micros', 'reserved_cost_micros - 1')->condition('id', $chatBucket)->execute();
foreach ([
  ['credit_grant_cost', 'committed_micros', 'grant_id', $third['grant_id'], 'grant_cost_projection_mismatch'],
  ['credit_allocation', 'returned_atoms', 'reservation_id', $held['reservation_id'], 'allocation_projection_mismatch'],
  ['credit_reservation', 'authorized_atoms', 'id', $held['reservation_id'], 'reservation_projection_mismatch'],
] as [$table, $field, $keyField, $id, $issue]) {
  $db->update($table)->expression($field, $field . ' + 1')->condition($keyField, $id)->execute();
  check(in_array($issue, $audit->inspect($uid)['issues']), 'Audit detects corruption: ' . $table);
  $db->update($table)->expression($field, $field . ' - 1')->condition($keyField, $id)->execute();
}
check($audit->inspect($uid)['status'] === 'complete', 'Restored projections agree with immutable movements');
check($audit->inspect($uid, 1)['status'] === 'limit_exceeded', 'Bounded audit never claims a partial history is complete');
$filter = ['from' => gmdate('c', $initialTime - 60), 'to' => gmdate('c', $clock->now + 1000), 'limit' => '100'];
$history = $query->transactions($filter)['rows'];
check(!array_diff(['grant', 'hold', 'consume', 'return', 'expire'], array_column($history, 'transaction_kind')), 'Own history includes multi-grant lifecycle journals');
check(!str_contains(json_encode($history), 'synthetic-final-proof') && !str_contains(json_encode($history), 'PRIVATE AUDIT'), 'Own history excludes execution proofs and internal notes');

$overrunHold = array_replace($failedHold, ['operation_id' => 'overrun', 'amount_atoms' => '100', 'cost_limit_micros' => '10']);
$life->reserve($overrunHold, 'overrun-hold');
$overrun = $life->settle(array_replace($finish, ['operation_id' => 'overrun', 'charged_atoms' => '0', 'actual_cost_micros' => '1000000']), 'overrun-final');
check($overrun['cost_limit_exceeded'] && budget($chatBucket)[2] === 1002100, 'Verified unexpected cost is recorded as a fact, not hidden or charged to the user');
check(in_array('budget_overrun', $audit->inspect($uid)['issues']), 'Audit explicitly reports supplier cost overrun');
denied(fn() => $life->reserve(array_replace($overrunHold, ['operation_id' => 'after-overrun']), 'after-overrun'), 'reward_budget_exhausted');
denied(fn() => $life->reserve(array_replace($overrunHold, ['operation_id' => 'zero-after-overrun', 'cost_limit_micros' => '0']), 'zero-after-overrun'), 'reward_budget_exhausted');
$clock->now += 101; $expiry->process();
check($query->account()['available_atoms'] === '0' && budget($chatBucket) === [0, 0, 1002100], 'Expiry still reduces liabilities after a cost overrun');
echo 'Lifecycle checks: ' . $checks . PHP_EOL;
