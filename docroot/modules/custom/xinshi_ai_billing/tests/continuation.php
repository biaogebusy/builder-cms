<?php

declare(strict_types=1);

use Drupal\xinshi_ai_billing\BillingProfiles;

// Loaded after the billing integration fixture; every row belongs to its disposable database.
function continuationRequest(string $root, string $parent): array {
  global $request;
  $link = ['root_operation_id' => $root, 'parent_operation_id' => $parent];
  return array_replace($request, ['continuation' => $link,
    'request_hash' => hash('sha256', BillingProfiles::json(['version' => 1, 'request_hash' => $request['request_hash']] + $link))]);
}

function continuationAttempt(string $root, string $authorization, string $attempt): void {
  global $billing, $producer, $uid;
  $billing->permit($producer, ['operation_id' => $root, 'authorization_id' => $authorization,
    'attempt_id' => $attempt, 'model' => 'fixture-model', 'account_ref' => 'fixture', 'role' => 'primary']);
  sourceRow('ai_provider_attempt', ['site_id' => 'fixture', 'operation_id' => $root,
    'attempt_id' => $attempt, 'logical_call_id' => $attempt, 'producer_id' => 'fixture-node', 'actor_user_id' => $uid,
    'payer' => 'platform', 'requested_model' => 'fixture-model', 'provider_account_ref' => 'fixture',
    'billing_role' => 'primary', 'usage_revision' => 1, 'state' => 'succeeded', 'dispatch_state' => 'sent',
    'usage_quality' => 'reported', 'input_tokens_total' => 100, 'output_tokens_total' => 25]);
  sourceRow('ai_usage_cost_entry', ['site_id' => 'fixture', 'attempt_id' => $attempt,
    'observation_revision' => 1, 'source_kind' => 'supplier_reconciliation', 'valuation_state' => 'reconciled',
    'currency' => 'CNY', 'total_cost_micros' => 55]);
}

foreach (['complete', 'cancel', 'unused'] as $scenario) {
  $root = 'continue-' . $scenario;
  $first = $billing->quote($request, $root);
  $billing->authorize(['operation_id' => $root, 'quote_id' => $first['quote_id'], 'request_hash' => $request['request_hash']]);
  $original = $db->select('ai_billing_authorization', 'a')->fields('a')->condition('operation_id', $root)->execute()->fetchAssoc();
  $nextRequest = continuationRequest($root, $root);
  denied(fn() => $billing->quote($nextRequest, $root . '-too-early'), 'authorization_not_exhausted');
  continuationAttempt($root, $root, $root . '-first');
  $db->update('ai_provider_attempt')->fields(['state' => 'unknown', 'dispatch_state' => 'unknown'])
    ->condition('attempt_id', $root . '-first')->execute();
  denied(fn() => $billing->quote($nextRequest, $root . '-unknown'), 'continuation_awaiting_evidence');
  $db->update('ai_provider_attempt')->fields(['state' => 'succeeded', 'dispatch_state' => 'sent'])
    ->condition('attempt_id', $root . '-first')->execute();
  $next = $billing->quote($nextRequest, $root . '-next');
  $principal->setAccount($other);
  denied(fn() => $billing->quote($nextRequest, $root . '-other-user'), 'operation_unavailable');
  denied(fn() => $billing->authorize(['operation_id' => $root . '-other-auth', 'quote_id' => $next['quote_id'],
    'request_hash' => $next['request_hash']]), 'quote_mismatch');
  $principal->setAccount($user);
  foreach (['requested_model' => 'other', 'provider_account_ref' => 'other', 'billing_role' => 'repair'] as $field => $value) {
    $beforeValue = $db->select('ai_provider_attempt', 'a')->fields('a', [$field])
      ->condition('attempt_id', $root . '-first')->execute()->fetchField();
    $db->update('ai_provider_attempt')->fields([$field => $value])->condition('attempt_id', $root . '-first')->execute();
    denied(fn() => $billing->quote($nextRequest, $root . '-mismatch-' . $field), 'continuation_awaiting_evidence');
    $db->update('ai_provider_attempt')->fields([$field => $beforeValue])->condition('attempt_id', $root . '-first')->execute();
  }
  denied(fn() => $billing->quote(array_replace($nextRequest, ['request_hash' => str_repeat('b', 64)]), $root . '-hash'), 'quote_mismatch');
  $expired = $billing->quote($nextRequest, $root . '-expired');
  $db->update('ai_billing_quote')->fields(['expires_at' => 1])->condition('id', $expired['quote_id'])->execute();
  $unchanged = snapshot();
  denied(fn() => $billing->authorize(['operation_id' => $root . '-expired-auth',
    'quote_id' => $expired['quote_id'], 'request_hash' => $expired['request_hash']]), 'quote_expired');
  check(snapshot() === $unchanged, 'An expired additional quote leaves the original hold intact');
  $alternative = $billing->quote($nextRequest, $root . '-alternative');
  $nextAccept = ['operation_id' => $root . '-authorization', 'quote_id' => $next['quote_id'], 'request_hash' => $next['request_hash']];
  $accepted = $billing->authorize($nextAccept);
  denied(fn() => $billing->permit($producer, ['operation_id' => $root, 'authorization_id' => $root . '-authorization',
    'attempt_id' => $root . '-first', 'model' => 'fixture-model', 'account_ref' => 'fixture', 'role' => 'primary']), 'permit_conflict');
  $before = snapshot();
  check($billing->authorize($nextAccept) === $accepted && snapshot() === $before, 'Repeated supplemental approval reserves once');
  check($original === $db->select('ai_billing_authorization', 'a')->fields('a')
    ->condition('operation_id', $root)->execute()->fetchAssoc(), 'Additional approval leaves the original authorization unchanged');
  check($billing->quote($nextRequest, $root . '-next') === $next, 'Supplemental quote projection remains immutable after approval');
  denied(fn() => $billing->authorize(['operation_id' => $root . '-duplicate', 'quote_id' => $alternative['quote_id'],
    'request_hash' => $alternative['request_hash']]), 'continuation_conflict');
  check(snapshot() === $before, 'A competing quote cannot create another hold for the same parent');
  $ids = [$root . '-first'];
  if ($scenario !== 'unused') {
    continuationAttempt($root, $root . '-authorization', $root . '-second');
    $ids[] = $root . '-second';
    denied(fn() => $billing->finalize($producer, ['operation_id' => $root, 'delivery' => 'none',
      'delivery_ref' => 'omitted', 'attempts' => [['attempt_id' => $root . '-first', 'revision' => 1]]]), 'manifest_incomplete');
    check($billing->own($root)['status'] === 'open', 'An omitted supplemental permit cannot freeze the root manifest');
  }
  $final = ['operation_id' => $root, 'delivery' => $scenario === 'cancel' ? 'none' : 'delivered',
    'delivery_ref' => 'synthetic:' . $root,
    'attempts' => array_map(static fn(string $id): array => ['attempt_id' => $id, 'revision' => 1], $ids)];
  if ($scenario === 'cancel') {
    $db->update('ai_provider_attempt')->fields(['state' => 'unknown', 'dispatch_state' => 'unknown'])
      ->condition('attempt_id', $root . '-second')->execute();
    check($billing->finalize($producer, $final)['status'] === 'awaiting_evidence', 'Cancellation cannot release unknown supplemental execution');
    check($billing->own($root)['charged_atoms'] === NULL, 'All grants remain awaiting source reconciliation');
    $db->update('ai_provider_attempt')->fields(['state' => 'succeeded', 'dispatch_state' => 'sent'])
      ->condition('attempt_id', $root . '-second')->execute();
  }
  $result = $billing->finalize($producer, $final);
  $charge = $scenario === 'cancel' ? '0' : ($scenario === 'unused' ? '150' : '300');
  check($result['authorized_atoms'] === '2400' && $result['charged_atoms'] === $charge,
    'Settlement partitions source attempts by their original permits');
  check(count($result['authorizations']) === 2 && $result['released_atoms'] === (string) (2400 - (int) $charge),
    'Every authorization settles or releases, including an unused approved segment');
  $before = snapshot();
  check($billing->finalize($producer, $final) === $result && snapshot() === $before, 'Whole-task finalization retry cannot charge either grant twice');
  $report = new \Drupal\xinshi_ai_billing\BillingReports($db, TRUE);
  $totals = $report->summarize('fixture', [$root, $root . '-authorization', $root], $uid);
  check($totals['operations'] === 1 && $totals['charged_atoms'] === $charge && $totals['held_atoms'] === '0',
    'Usage totals count one task and include all supplemental authorizations once');
  denied(fn() => $billing->quote(continuationRequest($root, $root . '-authorization'), $root . '-closed'), 'continuation_conflict');
}

$before = snapshot();
\Drupal::moduleHandler()->loadInclude('xinshi_ai_billing', 'install');
xinshi_ai_billing_update_11002(); xinshi_ai_billing_update_11002();
check(snapshot() === $before, 'Repeated supplemental schema updates preserve all existing financial records');
$schema = $db->schema();
$schema->renameTable('ai_billing_continuation', 'ai_billing_continuation_saved');
try {
  xinshi_ai_billing_update_11002(); xinshi_ai_billing_update_11002();
  check($schema->tableExists('ai_billing_continuation'), 'Upgrade installs a missing supplemental relation table');
  check(snapshot() === $before, 'Installing the supplemental table preserves original ledger and authorization data');
}
finally {
  $schema->dropTable('ai_billing_continuation');
  $schema->renameTable('ai_billing_continuation_saved', 'ai_billing_continuation');
}

$originalUser = $user; $originalUid = $uid;
$user = \Drupal\user\Entity\User::create(['name' => 'continuation-poor', 'status' => 1, 'roles' => ['ai_credit_user']]);
$user->save(); $uid = (string) $user->id(); $principal->setAccount($user);
\Drupal::service('xinshi_credits.ledger')->grant(command($pool, 'billing', 'gift', $uid, '1200'), 'continuation-poor-gift');
$root = 'continuation-poor';
$quote = $billing->quote($request, $root);
$billing->authorize(['operation_id' => $root, 'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']]);
continuationAttempt($root, $root, $root . '-attempt');
$quote = $billing->quote(continuationRequest($root, $root), $root . '-next');
$before = snapshot();
denied(fn() => $billing->authorize(['operation_id' => $root . '-extra', 'quote_id' => $quote['quote_id'],
  'request_hash' => $quote['request_hash']]), 'insufficient_credits');
check(snapshot() === $before, 'Insufficient additional balance preserves the original hold and quote');
$billing->finalize($producer, ['operation_id' => $root, 'delivery' => 'none', 'delivery_ref' => 'cancelled',
  'attempts' => [['attempt_id' => $root . '-attempt', 'revision' => 1]]]);
$user = $originalUser; $uid = $originalUid; $principal->setAccount($user);

// Keep an exhausted synthetic root for the real HTTP request parser regression.
$root = 'continuation-http';
$quote = $billing->quote($request, $root);
$billing->authorize(['operation_id' => $root, 'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']]);
continuationAttempt($root, $root, $root . '-first');
file_put_contents($fixture . '/continuation-http.json', json_encode(continuationRequest($root, $root), JSON_THROW_ON_ERROR));
