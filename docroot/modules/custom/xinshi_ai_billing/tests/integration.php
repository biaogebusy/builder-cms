<?php

declare(strict_types=1);

use Drupal\user\Entity\{Role, User};
use Drupal\xinshi_ai_billing\BillingProfiles;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';

\Drupal::service('module_installer')->install(['xinshi_ai_billing']);
$billingConfig = \Drupal::configFactory();
$billingConfig->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')
  ->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
$settings = $billingConfig->getEditable('xinshi_ai_billing.settings');
$billing = \Drupal::service('xinshi_ai_billing.billing');
$profiles = \Drupal::service('xinshi_ai_billing.profiles');
$principal = \Drupal::currentUser();
Role::create(['id' => 'ai_credit_user', 'label' => 'Synthetic AI credit user',
  'permissions' => ['use ai credits', 'view own credits']])->save();
$user = User::create(['name' => 'ai_credit_user', 'status' => 1, 'roles' => ['ai_credit_user']]);
$user->save(); $uid = (string) $user->id(); $principal->setAccount($user);
$pool = pool('billing', '100000', '10000000');
$bucket = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])
  ->condition('id', $pool)->execute()->fetchField(), TRUE)['chat']['bucket_id'];
\Drupal::service('xinshi_credits.ledger')->grant(command($pool, 'billing', 'gift', $uid, '100000'), 'billing-gift');
$profile = ['version' => 'synthetic-v1', 'feature' => 'text', 'model' => 'fixture-model',
  'platform' => 'fixture', 'category' => 'chat', 'currency' => 'CNY', 'cost_bucket_id' => $bucket,
  'evidence_ref' => 'SYNTHETIC ONLY', 'quote_ttl_seconds' => 60, 'calls' => [[
    'model' => 'fixture-model', 'account_ref' => 'fixture', 'role' => 'primary', 'max_calls' => 1,
    'max_input_tokens' => 1000, 'max_output_tokens' => 100, 'max_images' => 0,
    'cost_limit_micros' => '500', 'input_atoms_per_million' => '1000000',
    'output_atoms_per_million' => '2000000', 'atoms_per_image' => '0',
  ]]];
check($profiles->publish($profile) === $profiles->publish($profile), 'Price publication is immutable and idempotent');
$changed = $profile; $changed['calls'][0]['cost_limit_micros'] = '501';
denied(fn() => $profiles->publish($changed), 'price_version_conflict');
check(BillingProfiles::amounts($profile)['maximum_atoms'] === '1200', 'Ceiling covers all permitted inclusive tokens');
$request = ['feature' => 'text', 'model' => 'fixture-model', 'platform' => 'fixture',
  'request_hash' => str_repeat('a', 64), 'producer_id' => 'fixture-node'];
check($settings->get('enabled') === FALSE, 'Billing installs disabled');
denied(fn() => $billing->quote($request, 'first'), 'billing_disabled');
$settings->set('enabled', TRUE)->set('active_versions', ['synthetic-v1'])->save();
$quote = $billing->quote($request, 'first');
check($billing->quote($request, 'first') === $quote, 'Quote retry returns the original ID and expiry');
denied(fn() => $billing->quote(array_replace($request, ['request_hash' => str_repeat('b', 64)]), 'first'), 'quote_conflict');
$accept = ['quote_id' => $quote['quote_id'], 'operation_id' => 'billing-op', 'request_hash' => $request['request_hash']];
denied(fn() => $billing->authorize(array_replace($accept, ['request_hash' => str_repeat('b', 64)])), 'quote_mismatch');
$authorized = $billing->authorize($accept);
$before = snapshot();
check($billing->authorize($accept) === $authorized && snapshot() === $before, 'Authorization retry makes one atomic hold');
denied(fn() => $billing->authorize(array_replace($accept, ['operation_id' => 'another-op'])), 'idempotency_conflict');
$producer = ['producer_id' => 'fixture-node', 'site_id' => 'fixture'];
$permit = ['operation_id' => 'billing-op', 'attempt_id' => 'billing-attempt', 'model' => 'fixture-model',
  'account_ref' => 'fixture', 'role' => 'primary'];
denied(fn() => $billing->permit(['producer_id' => 'other', 'site_id' => 'fixture'], $permit), 'producer_denied');
denied(fn() => $billing->permit($producer, array_replace($permit, ['model' => 'costly-model'])), 'model_not_authorized');
check($billing->permit($producer, $permit) == $profile['calls'][0], 'Permit uses the originally accepted model bounds');
check($billing->permit($producer, $permit) == $profile['calls'][0], 'Permit redelivery does not spend another call');
denied(fn() => $billing->permit($producer, array_replace($permit, ['attempt_id' => 'second-attempt'])), 'authorization_exceeded');
$final = ['operation_id' => 'billing-op', 'delivery' => 'delivered', 'delivery_ref' => 'synthetic-durable-run',
  'attempts' => [['attempt_id' => 'billing-attempt', 'revision' => 1]]];
denied(fn() => $billing->finalize($producer, array_replace($final, ['attempts' => []])), 'manifest_incomplete');
check($billing->finalize($producer, $final)['pending_reason'] === 'usage_incomplete', 'Finalization before usage keeps the hold');
denied(fn() => $billing->permit($producer, $permit), 'operation_closed');

// Fixture rows represent independent metering projections, not client-supplied billing amounts.
function sourceRow(string $table, array $fields): int {
  \Drupal::moduleHandler()->loadInclude('xinshi_ai_usage', 'install');
  $schema = xinshi_ai_usage_schema()[$table]['fields'];
  foreach ($schema as $name => $definition) {
    if (array_key_exists($name, $fields) || $definition['type'] === 'serial'
      || !($definition['not null'] ?? FALSE) || array_key_exists('default', $definition)) continue;
    $fields[$name] = $definition['type'] === 'int' ? 1 : 'synthetic';
  }
  return (int) \Drupal::database()->insert($table)->fields($fields)->execute();
}
$attempt = sourceRow('ai_provider_attempt', ['site_id' => 'fixture', 'operation_id' => 'billing-op',
  'attempt_id' => 'billing-attempt', 'producer_id' => 'fixture-node', 'actor_user_id' => $uid,
  'payer' => 'platform', 'requested_model' => 'fixture-model', 'resolved_model' => 'fixture-model',
  'provider_account_ref' => 'fixture', 'billing_role' => 'primary', 'usage_revision' => 1,
  'state' => 'unknown', 'dispatch_state' => 'unknown', 'usage_quality' => 'missing']);
check($billing->finalize($producer, $final)['pending_reason'] === 'execution_unresolved', 'Unknown execution cannot release or settle');
$db->update('ai_provider_attempt')->fields(['state' => 'succeeded', 'dispatch_state' => 'sent',
  'usage_quality' => 'reported', 'input_tokens_total' => 100, 'output_tokens_total' => 25])->condition('id', $attempt)->execute();
sourceRow('ai_usage_cost_entry', ['site_id' => 'fixture', 'attempt_id' => 'billing-attempt',
  'observation_revision' => 1, 'source_kind' => 'price_book', 'valuation_state' => 'rated_estimate',
  'currency' => 'CNY', 'total_cost_micros' => 50]);
check($billing->finalize($producer, $final)['pending_reason'] === 'cost_unresolved', 'Estimated procurement cost is never final cost');
sourceRow('ai_usage_cost_entry', ['site_id' => 'fixture', 'attempt_id' => 'billing-attempt',
  'observation_revision' => 1, 'source_kind' => 'supplier_reconciliation', 'valuation_state' => 'reconciled',
  'currency' => 'CNY', 'total_cost_micros' => 55]);
$settings->set('enabled', FALSE)->save();
$settled = $billing->finalize($producer, $final);
check($settled['status'] === 'settled' && $settled['charged_atoms'] === '150'
  && $settled['released_atoms'] === '1050', 'Verified usage settles the original price even while admission is off');
$before = snapshot();
check($billing->finalize($producer, $final) === $settled && snapshot() === $before, 'Finalization retry cannot double charge');
check($billing->own('billing-op') === $settled, 'Owner sees the immutable ledger outcome');
check(!str_contains(json_encode($settled), 'cost') && !str_contains(json_encode($settled), 'evidence'), 'Personal results exclude supplier evidence');
$other = User::create(['name' => 'ai_credit_other', 'status' => 1, 'roles' => ['ai_credit_user']]); $other->save();
$principal->setAccount($other);
denied(fn() => $billing->own('billing-op'), 'operation_unavailable');
denied(fn() => $billing->authorize($accept), 'quote_mismatch');
$principal->setAccount($user);
$settings->set('enabled', TRUE)->save();
$quote2 = $billing->quote($request, 'no-dispatch');
$billing->authorize(array_replace($accept, ['quote_id' => $quote2['quote_id'], 'operation_id' => 'no-dispatch']));
$release = $billing->finalize($producer, ['operation_id' => 'no-dispatch', 'delivery' => 'none',
  'delivery_ref' => 'synthetic-cancel-before-dispatch', 'attempts' => []]);
check($release['status'] === 'released' && $release['released_atoms'] === '1200', 'Confirmed unstarted execution releases once');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Billing preserves ledger and budget reconstruction');

$trigger = $db->driver() === 'mysql'
  ? "CREATE TRIGGER reject_ai_auth BEFORE INSERT ON {ai_billing_authorization} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic auth failure'"
  : "CREATE TRIGGER reject_ai_auth BEFORE INSERT ON {ai_billing_authorization} BEGIN SELECT RAISE(ABORT, 'synthetic auth failure'); END";
$quote3 = $billing->quote($request, 'atomic-failure');
$before = snapshot(); $failed = FALSE;
$db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
try { $billing->authorize(array_replace($accept, ['quote_id' => $quote3['quote_id'], 'operation_id' => 'atomic-failure'])); }
catch (Throwable) { $failed = TRUE; }
$db->query('DROP TRIGGER reject_ai_auth');
check($failed && snapshot() === $before, 'Adapter insert failure rolls back every ledger and budget mutation');
check(count(\Drupal::service('xinshi_ai_billing.uninstall')->validate('xinshi_ai_billing')) === 1, 'Billing records prevent destructive uninstall');
$report = \Drupal::service('xinshi_ai_billing.reports');
$totals = $report->summarize('fixture', ['billing-op', 'billing-op', 'no-dispatch'], $uid);
check($totals['charged_atoms'] === '150' && $totals['operations'] === 2 && $totals['held_atoms'] === '0',
  'Usage credit totals count whole operations once');
check($report->summarize('fixture', ['billing-op'], (string) $other->id())['charged_atoms'] === '0',
  'User report scope cannot include another ledger owner');
$settings->set('enabled', FALSE)->save();
check($report->summarize('fixture', ['billing-op'], $uid)['status'] === 'available', 'Historical charges stay visible after admission is disabled');
check($report->summarize('another-site', ['billing-op'], $uid)['status'] === 'not_enabled', 'Credit reports do not cross site boundaries');
$settings->set('enabled', TRUE)->save();
echo 'AI billing checks: ' . $checks . PHP_EOL;
// The fixture kernel does not dump containers; discard the earlier HTTP definition.
\Drupal::service('kernel')->invalidateContainer();
