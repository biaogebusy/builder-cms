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
$uid = $originalUid; $principal->setAccount($user);
