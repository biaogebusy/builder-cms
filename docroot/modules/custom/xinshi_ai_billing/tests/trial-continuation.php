<?php

declare(strict_types=1);

use Drupal\user\Entity\User;
use Drupal\xinshi_ai_billing\BillingProfiles;

// Extend the isolated points fixture after its original balance assertions.
$billing = fixtureBilling();
$participant = User::create(['name' => 'trial-continuation', 'status' => 1, 'roles' => ['trial_participant']]);
$participant->save();
$originalUid = $uid; $uid = (string) $participant->id();
$principal->setAccount($participant);
$rewards->daily('trial-continuation-welcome', 'G13');
foreach (['complete', 'cancel', 'unused', 'failed'] as $scenario) {
  $root = 'points-continue-' . $scenario;
  $first = $billing->quote($request, $root);
  $billing->authorize(['operation_id' => $root, 'quote_id' => $first['quote_id'], 'request_hash' => $first['request_hash']]);
  $ids = [];
  for ($i = 0; $i < 8; $i++) {
    $ids[] = $root . '-' . $i;
    trialAttempt($root, end($ids));
  }
  $link = ['root_operation_id' => $root, 'parent_operation_id' => $root];
  $additional = array_replace($request, ['continuation' => $link,
    'request_hash' => hash('sha256', BillingProfiles::json(['version' => 1, 'request_hash' => $request['request_hash']] + $link))]);
  $quote = $billing->quote($additional, $root . '-next');
  $accept = ['operation_id' => $root . '-auth', 'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']];
  $billing->authorize($accept);
  if ($scenario !== 'unused') {
    $id = $root . '-extra'; $ids[] = $id;
    $billing->permit($producer, ['operation_id' => $root, 'authorization_id' => $root . '-auth',
      'attempt_id' => $id, 'model' => 'trial-chat', 'account_ref' => 'fixture', 'role' => 'primary']);
    trialSource('ai_provider_attempt', ['site_id' => 'trial-fixture', 'operation_id' => $root,
      'attempt_id' => $id, 'logical_call_id' => $id, 'producer_id' => 'trial-node', 'actor_user_id' => $uid,
      'payer' => 'platform', 'requested_model' => 'trial-chat', 'provider_account_ref' => 'fixture',
      'billing_role' => 'primary', 'usage_revision' => 1, 'dispatch_state' => 'sent',
      'state' => $scenario === 'failed' ? 'failed' : 'succeeded', 'usage_quality' => 'missing']);
  }
  $final = trialFinal($root, $ids, $scenario === 'cancel' ? 'none' : 'delivered');
  $result = $billing->finalize($producer, $final);
  $charged = (int) $first['maximum_atoms'] * ($scenario === 'cancel' ? 0 : ($scenario === 'complete' ? 2 : 1));
  check($result['charged_atoms'] === (string) $charged && $result['authorized_atoms'] === (string) ((int) $first['maximum_atoms'] * 2),
    'Points continuation charges each delivered successful segment once: ' . $scenario);
  check($result['released_atoms'] === (string) ((int) $result['authorized_atoms'] - $charged),
    'Cancelled, failed and unused segments return held points: ' . $scenario);
  $before = snapshot();
  check($billing->finalize($producer, $final) === $result && snapshot() === $before, 'Points continuation settlement is idempotent');
}
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Supplemental points reconstruct from the original ledger');

// Store real usage events but leave their projection to the first supplemental quote.
$root = 'points-first-quote';
$source = array_replace($producer, ['producer_id' => $usage::PRODUCER_ID]);
$initial = array_replace($request, ['producer_id' => $source['producer_id']]);
$first = $billing->quote($initial, $root);
$billing->authorize(['operation_id' => $root, 'quote_id' => $first['quote_id'], 'request_hash' => $initial['request_hash']]);
$intent = ['feature' => 'common', 'stage' => 'chat', 'payer' => 'platform', 'billing_role' => 'primary',
  'provider_account_ref' => 'fixture', 'requested_model' => 'trial-chat', 'actor_user_id' => $uid];
$usage->prepare($intent + ['operation_id' => 'unrelated-first-quote', 'logical_call_id' => 'unrelated']);
$link = ['root_operation_id' => $root, 'parent_operation_id' => $root];
$additional = array_replace($initial, ['continuation' => $link,
  'request_hash' => hash('sha256', BillingProfiles::json(['version' => 1, 'request_hash' => $initial['request_hash']] + $link))]);
$ids = [];
for ($i = 0; $i < 8; $i++) {
  $id = $usage->prepare($intent + ['operation_id' => $root, 'logical_call_id' => "call-$i"])['attempt_id'];
  $ids[] = $id;
  $billing->permit($source, ['operation_id' => $root, 'attempt_id' => $id,
    'model' => 'trial-chat', 'account_ref' => 'fixture', 'role' => 'primary']);
  $usage->observe($id, ['dispatch_state' => 'sent', 'outcome' => 'succeeded']);
}
$before = snapshot();
$pending = static fn(string $op) => (int) $db->select('ai_usage_event', 'e')->condition('operation_id', $op)
  ->isNull('processed_at')->countQuery()->execute()->fetchField();
check($pending($root) === 16, 'Eight permits have arrived with no projected observations');
$quote = $billing->quote($additional, $root . '-first');
check($pending($root) === 0 && $pending('unrelated-first-quote') === 1 && snapshot() === $before,
  'The first quote projects only the requested operation and makes no ledger entries');
$accept = ['operation_id' => $root . '-extra', 'quote_id' => $quote['quote_id'], 'request_hash' => $quote['request_hash']];
$authorized = $billing->authorize($accept);
$held = snapshot();
check($billing->authorize($accept) === $authorized && snapshot() === $held,
  'An explicit approval after catch-up holds the supplemental amount once');
$settled = $billing->finalize($source, trialFinal($root, $ids));
check($settled['charged_atoms'] === $first['maximum_atoms'] && $settled['released_atoms'] === $quote['maximum_atoms'],
  'Settlement charges the used segment and releases the unused supplemental amount');
$uid = $originalUid; $principal->setAccount($user);
