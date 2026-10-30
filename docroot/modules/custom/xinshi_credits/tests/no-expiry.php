<?php

declare(strict_types=1);

use Drupal\xinshi_credits\CreditBudgets;
use Drupal\xinshi_credits\CreditStore;
use Drupal\xinshi_credits\CreditLedger;
use Drupal\xinshi_credits\CreditLifecycle;
use Drupal\xinshi_credits\CreditQuery;
use Drupal\xinshi_credits\CreditExpiry;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures.php';
require_once '/app/docroot/modules/custom/xinshi_credits/xinshi_credits.install';
$before = $db->select('credit_grant', 'g')->fields('g')->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC);
$definition = xinshi_credits_schema()['credit_grant']['fields']['expires_at'];
$definition['not null'] = TRUE; $definition['default'] = 0;
$db->schema()->changeField('credit_grant', 'expires_at', 'expires_at', $definition);
xinshi_credits_update_11003(); xinshi_credits_update_11003();
check($before === $db->select('credit_grant', 'g')->fields('g', array_keys($before[0]))->orderBy('id')->execute()->fetchAll(PDO::FETCH_ASSOC), 'Nullable expiry upgrade preserves all existing grants and dates');
$expirySettings = \Drupal::configFactory()->getEditable('xinshi_credits.settings');
$original = $expirySettings->getRawData();
$expirySettings->set('site_id', 'fixture')->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
$clock = new FixtureClock(time()); $uuid = \Drupal::service('uuid');
$context = \Drupal::service('xinshi_credits.context'); $entities = \Drupal::entityTypeManager();
$budgets = new CreditBudgets($db, $context, $clock);
$store = new CreditStore($db, $context, $clock, $uuid);
$ledger = new CreditLedger($db, $context, $uuid, $entities, $budgets, $store);
$life = new CreditLifecycle($db, $context, $store, $budgets, $uuid, $entities);
$expiry = new CreditExpiry($db, $context, $life, $clock, \Drupal::logger('xinshi_credits'));
$user = \Drupal\user\Entity\User::create(['name' => 'nonexpiring', 'status' => 1, 'roles' => ['credit_reader']]); $user->save();
\Drupal::currentUser()->setAccount($user); $uid = (string) $user->id();
$query = new CreditQuery($db, $context, $clock, \Drupal::service('private_key'), \Drupal::currentUser(), $entities);
$p = pool('no-expiry');
$bounds = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])->condition('id', $p)->execute()->fetchField(), TRUE);
$bucket = $bounds['chat']['bucket_id'];
$gift = command($p, 'no-expiry', 'forever', $uid, '900'); unset($gift['expires_at']);
$forever = $ledger->grant($gift, 'forever');
check($ledger->grant($gift, 'forever') === $forever, 'No-expiry grant retries preserve the original receipt');
$dated = $ledger->grant(array_replace($gift, ['source_ref' => 'dated', 'amount_atoms' => '100', 'expires_at' => $clock->now + 100]), 'dated');
$common = ['beneficiary_user_id' => $uid, 'actor_user_id' => '1', 'public_reason' => 'Synthetic expiry checks'];
$hold = $common + ['operation_id' => 'no-expiry-op', 'quote_id' => 'quote', 'authorization_version' => 'v1',
  'quote_expires_at' => $clock->now + 60, 'category' => 'chat', 'amount_atoms' => '200',
  'cost_bucket_id' => $bucket, 'cost_limit_micros' => '200', 'cost_currency' => 'CNY'];
$receipt = $life->reserve($hold, 'no-expiry-hold');
$ids = $db->select('credit_allocation', 'a')->fields('a', ['grant_id'])->condition('reservation_id', $receipt['reservation_id'])->orderBy('position')->execute()->fetchCol();
check($ids === [$dated['grant_id'], $forever['grant_id']], 'Dated credits are spent before nonexpiring credits');
$clock->now += 2 * 86400;
$release = $life->release($common + ['operation_id' => 'no-expiry-op', 'outcome' => 'not_started', 'evidence_ref' => 'Synthetic no execution'], 'no-expiry-release');
check($release['released_atoms'] === '100' && $release['expired_atoms'] === '100', 'Late release restores nonexpiring points and expires only dated points');
$expiry->process();
check($query->account()['available_atoms'] === '900', 'Cron and period boundaries do not expire undated grants');
denied(fn() => $life->expire($common + ['grant_id' => $forever['grant_id']], 'cannot-expire'), 'grant_not_expired');
$lateHold = array_replace($hold, ['operation_id' => 'later-period', 'quote_expires_at' => $clock->now + 60]);
denied(fn() => $life->reserve($lateHold, 'expired-budget'), 'cost_budget_unavailable');
$new = $db->select('credit_budget_bucket', 'b')->fields('b')->condition('id', $bucket)->execute()->fetchAssoc();
$new['id'] = $uuid->generate(); $new['period_start'] = $clock->now - 1; $new['period_end'] = $clock->now + 86400;
$new['committed_cost_micros'] = 0; $new['reserved_cost_micros'] = 0; $new['actual_cost_micros'] = 0;
$db->insert('credit_budget_bucket')->fields($new)->execute();
$lateHold['cost_bucket_id'] = $new['id']; $life->reserve($lateHold, 'later-budget');
$finish = $common + ['operation_id' => 'later-period', 'outcome' => 'completed', 'evidence_ref' => 'Synthetic final cost',
  'charged_atoms' => '120', 'actual_cost_micros' => '50', 'cost_status' => 'final', 'cost_currency' => 'CNY'];
$life->settle($finish, 'later-settle');
$life->returnCredits($common + ['operation_id' => 'later-period', 'return_ref' => 'correction',
  'returns' => [['grant_id' => $forever['grant_id'], 'amount_atoms' => '20']]], 'later-return');
check($query->account()['available_atoms'] === '800', 'Later-period settlement and return preserve nonexpiring balances');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Cross-period obligations and new execution reconstruct from the ledger');
$history = $query->transactions(['from' => gmdate('c', time() - 60), 'to' => gmdate('c', $clock->now + 60)])['rows'];
$rows = array_filter($history, fn($row) => $row['journal_id'] === $forever['journal_id']);
check(count($rows) === 1 && current($rows)['expires_at'] === NULL, 'Public history represents no expiry with NULL');
$expirySettings->setData($original)->save();
echo 'No-expiry checks: ' . $checks . PHP_EOL;
