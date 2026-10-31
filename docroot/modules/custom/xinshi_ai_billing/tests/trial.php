<?php

declare(strict_types=1);

use Drupal\Core\Site\Settings;
use Drupal\user\Entity\{Role, User};
use Drupal\xinshi_ai_billing\{BillingProfiles, TrialSetup};

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, (string) $error . PHP_EOL); exit(1); });
$trialConfig = \Drupal::configFactory();
new Settings(['xinshi_ai_usage.mode' => 'enforce', 'xinshi_ai_usage.site_id' => 'trial-fixture',
  'xinshi_ai_usage.producers' => ['trial-node' => ['site_id' => 'trial-fixture', 'secret' => 'synthetic-only']]]);
$trialConfig->getEditable('xinshi_credits.settings')->set('site_id', 'trial-fixture')->set('grants_enabled', FALSE)->set('spending_enabled', FALSE)->save();
$trialConfig->getEditable('xinshi_ai_billing.settings')->set('enabled', FALSE)->set('active_versions', [])->save();
$trialConfig->getEditable('xinshi_rewards.settings')->set('business_timezone', 'UTC')->save();
$db->update('credit_reward_calendar')->fields(['timezone' => 'UTC'])->condition('id', 1)->execute();
$trialConfig->getEditable('xinshi_ai.models')->setData(['platforms' => ['fixture' => ['enabled' => TRUE]],
  'models' => [
    ['id' => 'trial-chat', 'platform' => 'fixture', 'capabilities' => ['chat']],
    ['id' => 'trial-image', 'platform' => 'fixture', 'capabilities' => ['image'], 'sizes' => ['1024x1024', '1536x1024'], 'max_n' => 4],
  ], 'defaults' => ['chat' => 'trial-chat', 'critic' => 'trial-chat', 'classifier' => 'trial-chat', 'image' => 'trial-image']])->save();
Role::create(['id' => 'trial_admin', 'label' => 'Trial administrator', 'permissions' => TrialSetup::PERMISSIONS])->save();
Role::create(['id' => 'trial_participant', 'label' => 'Trial participant'])->save();
$admin = User::create(['name' => 'trial_admin', 'status' => 1, 'roles' => ['trial_admin'], 'pass' => 'synthetic-pass']); $admin->save();
$user = User::create(['name' => 'trial_participant', 'status' => 1, 'roles' => ['trial_participant']]); $user->save(); $uid = (string) $user->id();
$principal = \Drupal::currentUser(); $principal->setAccount($admin);
$setup = \Drupal::service('xinshi_ai_billing.trial');
$input = ['welcome' => '15', 'daily' => '1', 'chat' => '1', 'image' => '1', 'chat_model' => 'trial-chat',
  'image_model' => 'trial-image', 'role' => 'trial_participant', 'timezone' => 'UTC', 'expires_at' => NULL];
$before = snapshot();
$draft = $setup->prepare($input, 'trial-fixture-v1', time());
check(snapshot() === $before && !$trialConfig->get('xinshi_ai_billing.settings')->get('enabled'), 'Trial review makes no financial writes and does not enable billing');
denied(fn() => $setup->prepare(array_replace($input, ['welcome' => '14']), 'small', time()), 'trial_experience_too_small');
denied(fn() => $setup->prepare(array_replace($input, ['role' => 'anonymous']), 'anon', time()), 'invalid_trial_role');
$principal->setAccount($user);
denied(fn() => $setup->save($draft), 'permission_denied');
$principal->setAccount($admin);
$failSave = static function (\Drupal\Core\Config\ConfigCrudEvent $event): void {
  if ($event->getConfig()->getName() === 'xinshi_ai_billing.trial') throw new RuntimeException('Synthetic config failure');
};
$dispatcher = \Drupal::service('event_dispatcher');
$dispatcher->addListener(\Drupal\Core\Config\ConfigEvents::SAVE, $failSave);
$before = snapshot();
try { $setup->save($draft); throw new RuntimeException('Expected setup failure'); }
catch (RuntimeException $error) {
  check($error->getMessage() === 'Synthetic config failure' && snapshot() === $before,
    'Failed setup rolls back both published pools and all ledger changes');
  check(!$trialConfig->get('xinshi_ai_billing.trial')->get('version')
    && !$db->select('ai_billing_profile', 'p')->condition('site_id', 'trial-fixture')->countQuery()->execute()->fetchField(),
    'Failed setup leaves no active config or orphan price versions');
}
$dispatcher->removeListener(\Drupal\Core\Config\ConfigEvents::SAVE, $failSave);
$setup->save($draft); $before = snapshot(); $setup->save($draft);
check(snapshot() === $before, 'Saving the same reviewed trial version is idempotent');
check($trialConfig->get('xinshi_credits.settings')->get('spending_enabled') && $trialConfig->get('xinshi_rewards.settings')->get('welcome_enabled'), 'Quick setup enables the reviewed policy without granting credits');
check((int) $db->select('credit_account', 'a')->condition('site_id', 'trial-fixture')->countQuery()->execute()->fetchField() === 0, 'Configuration does not initialize or credit participants');
$user = User::load($uid); $principal->setAccount($user);
$rewards = \Drupal::service('xinshi_rewards.rewards');
$welcome = $rewards->daily('trial-welcome', 'G13');
$daily = $rewards->daily('trial-daily');
check($welcome['receipt']['amount_atoms'] === '15000' && $daily['receipt']['amount_atoms'] === '1000', 'First and daily rewards issue 15 plus 1 points through the shared ledger');
check($rewards->daily('trial-welcome-again', 'G13')['status'] === 'already_granted', 'Another welcome key cannot issue a second grant');
check($rewards->daily('trial-daily-again')['status'] === 'already_granted', 'Another daily key cannot issue a second grant');
check($db->select('credit_grant', 'g')->fields('g', ['expires_at'])->condition('id', $welcome['receipt']['grant_id'])->execute()->fetchField() === NULL, 'Trial rewards do not expire by default');
$billing = \Drupal::service('xinshi_ai_billing.billing');
$profiles = \Drupal::service('xinshi_ai_billing.profiles');
$producer = ['producer_id' => 'trial-node', 'site_id' => 'trial-fixture'];
$request = ['feature' => 'fullHTML', 'model' => 'trial-chat', 'platform' => 'fixture', 'request_hash' => str_repeat('a', 64), 'producer_id' => 'trial-node'];
function trialAuthorize(string $operation): array {
  global $billing, $request;
  $quote = $billing->quote($request, $operation);
  check($quote['maximum_atoms'] === '1000', 'Flat UI quote reserves one point, independent of token and call ceilings');
  $billing->authorize(['quote_id' => $quote['quote_id'], 'operation_id' => $operation, 'request_hash' => $request['request_hash']]);
  return $quote;
}
function trialSource(string $table, array $fields): int {
  \Drupal::moduleHandler()->loadInclude('xinshi_ai_usage', 'install');
  foreach (xinshi_ai_usage_schema()[$table]['fields'] as $key => $definition) {
    if (array_key_exists($key, $fields) || $definition['type'] === 'serial' || !($definition['not null'] ?? FALSE) || array_key_exists('default', $definition)) continue;
    $fields[$key] = $definition['type'] === 'int' ? 1 : 'synthetic';
  }
  return (int) \Drupal::database()->insert($table)->fields($fields)->execute();
}
function trialAttempt(string $op, string $id, string $state = 'succeeded'): int {
  global $billing, $producer, $uid;
  $billing->permit($producer, ['operation_id' => $op, 'attempt_id' => $id, 'model' => 'trial-chat', 'account_ref' => 'fixture', 'role' => 'primary']);
  return trialSource('ai_provider_attempt', ['site_id' => 'trial-fixture', 'operation_id' => $op, 'attempt_id' => $id, 'logical_call_id' => $id,
    'producer_id' => 'trial-node', 'actor_user_id' => $uid, 'payer' => 'platform', 'requested_model' => 'trial-chat',
    'provider_account_ref' => 'fixture', 'billing_role' => 'primary', 'usage_revision' => 1,
    'state' => $state, 'dispatch_state' => $state === 'unknown' ? 'unknown' : 'sent', 'usage_quality' => 'missing']);
}
function trialFinal(string $op, array $ids, string $delivery = 'delivered'): array {
  return ['operation_id' => $op, 'delivery' => $delivery, 'delivery_ref' => 'synthetic:' . $op,
    'attempts' => array_map(static fn(string $id): array => ['attempt_id' => $id, 'revision' => 1], $ids)];
}
trialAuthorize('trial-success');
trialAttempt('trial-success', 'trial-attempt-a'); trialAttempt('trial-success', 'trial-attempt-b');
$final = trialFinal('trial-success', ['trial-attempt-a', 'trial-attempt-b']);
$result = $billing->finalize($producer, $final);
check($result['charged_atoms'] === '1000' && $result['status'] === 'settled', 'Two confirmed primary calls charge one successful operation without token or supplier cost evidence');
$before = snapshot(); check($billing->finalize($producer, $final) === $result && snapshot() === $before, 'Repeated finalization does not charge again');
$row = $db->select('credit_reservation', 'r')->fields('r')->condition('operation_id', 'trial-success')->execute()->fetchAssoc();
check($row['actual_cost_micros'] === NULL && $row['cost_bucket_id'] === NULL, 'Points-only settlement leaves supplier cost unrecorded, never final zero');
trialAuthorize('trial-failed'); trialAttempt('trial-failed', 'trial-attempt-failed', 'failed');
$result = $billing->finalize($producer, trialFinal('trial-failed', ['trial-attempt-failed'], 'none'));
check($result['charged_atoms'] === '0' && $result['released_atoms'] === '1000', 'Confirmed failed execution returns all held credits');
trialAuthorize('trial-unknown'); $id = trialAttempt('trial-unknown', 'trial-attempt-unknown', 'unknown');
$unknown = trialFinal('trial-unknown', ['trial-attempt-unknown'], 'none');
check($billing->finalize($producer, $unknown)['pending_reason'] === 'execution_unresolved', 'Unknown execution retains the hold');
$principal->setAccount($admin);
$stale = $setup->prepare($input, 'trial-stale', time());
$next = $setup->prepare(array_replace($input, ['chat' => '2', 'welcome' => '20']), 'trial-fixture-v2', time()); $setup->save($next);
denied(fn() => $setup->save($stale), 'trial_configuration_changed');
$db->update('ai_provider_attempt')->fields(['state' => 'failed', 'dispatch_state' => 'sent'])->condition('id', $id)->execute();
check($billing->finalize($producer, $unknown)['released_atoms'] === '1000', 'An old held task closes under the original profile after a policy change');
$principal->setAccount($user);
check($rewards->daily('new-version-welcome', 'G13')['status'] === 'already_granted', 'A new trial policy cannot reopen welcome eligibility');
check($rewards->daily('new-version-daily')['status'] === 'already_granted', 'A new trial policy cannot reopen daily eligibility');
$account = \Drupal::service('xinshi_credits.query')->account();
check($account['available_atoms'] === '15000', 'Shared balance reflects gifts and exactly one charged operation');
$common = ['beneficiary_user_id' => $uid, 'actor_user_id' => $uid, 'public_reason' => 'Synthetic points test'];
$lifecycle = \Drupal::service('xinshi_credits.lifecycle');
$hold = $common + ['operation_id' => 'mixed-hold', 'quote_id' => 'mixed-q', 'authorization_version' => 'v1',
  'quote_expires_at' => time() + 60, 'category' => 'chat', 'amount_atoms' => '1000',
  'cost_bucket_id' => 'not-a-budget', 'cost_currency' => 'CNY', 'cost_limit_micros' => '1'];
denied(fn() => $lifecycle->reserve($hold, 'mixed-hold'), 'insufficient_credits');
$hold = array_diff_key($hold, array_flip(['cost_bucket_id', 'cost_currency', 'cost_limit_micros'])) + ['accounting_mode' => 'points'];
$lifecycle->reserve($hold, 'points-hold');
denied(fn() => $lifecycle->settle($common + ['operation_id' => 'mixed-hold', 'outcome' => 'completed', 'evidence_ref' => 'synthetic',
  'charged_atoms' => '1000', 'actual_cost_micros' => '0', 'cost_currency' => 'CNY', 'cost_status' => 'final'], 'wrong-mode'), 'accounting_mode_mismatch');
$lifecycle->settle($common + ['operation_id' => 'mixed-hold', 'outcome' => 'completed', 'evidence_ref' => 'synthetic',
  'charged_atoms' => '1000', 'accounting_mode' => 'points'], 'points-settle');
$lifecycle->returnCredits($common + ['operation_id' => 'mixed-hold', 'return_ref' => 'synthetic-return',
  'returns' => [['grant_id' => $welcome['receipt']['grant_id'], 'amount_atoms' => '1000']]], 'points-return');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Point grants, holds, returns and costs reconstruct through the shared audit');
check((int) $db->select('credit_budget_bucket', 'b')->condition('site_id', 'trial-fixture')->countQuery()->execute()->fetchField() === 0, 'Trial setup and activity create no currency budget');

// Exercise real image entities and delivery records without a provider call or supplier invoice.
$loader->addPsr4('Drupal\\xinshi_ai\\', __DIR__ . '/../../xinshi_ai/src');
$runs = new \Drupal\xinshi_ai\Service\ImageJobRuns($db, \Drupal::time(), \Drupal::service('uuid'));
$usage = \Drupal::service('xinshi_ai_usage.local_producer');
$images = new \Drupal\xinshi_ai_billing\ImageBilling($billing, $profiles, $db, $trialConfig,
  \Drupal::service('xinshi_credits.context'), \Drupal::entityTypeManager(), $usage, \Drupal::time(), NULL, $runs);
$recorder = new \Drupal\xinshi_ai\Service\ImageUsageRecorder($usage, \Drupal::logger('xinshi_ai_usage'), $images);
$imageInput = ['jobKind' => 'text_to_image', 'platform' => 'fixture', 'model' => 'trial-image',
  'prompt' => 'Synthetic image', 'params' => ['n' => 2, 'size' => '1536*1024']];
$imageQuote = $images->quote($imageInput, 'trial-image-quote')['quote'];
check($imageQuote['maximum_atoms'] === '2000', 'Trial image quote reserves only the requested count at the registered size');
denied(fn() => $images->quote(array_replace($imageInput, ['params' => ['n' => 5, 'size' => '1024x1024']]), 'trial-many-images'), 'image_request_outside_quote');
denied(fn() => $images->quote(array_replace($imageInput, ['params' => ['n' => 1, 'size' => '8x8']]), 'trial-wrong-size'), 'image_request_outside_quote');
$imageJob = $images->create($imageInput, $imageQuote['quote_id'], static function (string $op) use ($uid, $imageInput): \Drupal\node\NodeInterface {
  $job = \Drupal\node\Entity\Node::create(['type' => 'image_job', 'uuid' => $op, 'uid' => $uid, 'title' => 'Synthetic trial',
    'field_job_kind' => 'text_to_image', 'field_platform' => 'fixture', 'field_model' => 'trial-image',
    'field_prompt' => $imageInput['prompt'], 'field_params' => json_encode($imageInput['params']),
    'field_n_requested' => 2, 'field_n_succeeded' => 0, 'field_status' => 'queued']);
  $job->save(); return $job;
});
$images->beforeRun($imageJob, ['n' => 2, 'size' => '1536x1024', 'user' => $uid]);
$imageAttempt = $recorder->begin(['operation_id' => $imageJob->uuid(), 'kind' => 'text_to_image',
  'platform' => 'fixture', 'model' => 'trial-image', 'actor_user_id' => $uid, 'task_id' => NULL]);
$usage->observe($imageAttempt->attemptId, ['outcome' => 'succeeded', 'dispatch_state' => 'sent',
  'usage' => \Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer::normalize(['data' => [['url' => 'synthetic']]]), 'error_code' => NULL]);
\Drupal::service('xinshi_ai_usage.projection')->process('trial-fixture');
$imageJob->set('field_status', 'succeeded')->set('field_n_succeeded', 1)->save();
$images->reconcile();
check($billing->own($imageJob->uuid())['status'] === 'awaiting_evidence', 'Trial image still waits for a committed delivery');
$imageAttempt->committed(0, 'synthetic-trial-asset');
$trialConfig->getEditable('xinshi_ai_billing.settings')->set('enabled', FALSE)->save();
$images->reconcile();
$result = $billing->own($imageJob->uuid());
check($result['charged_atoms'] === '1000' && $result['released_atoms'] === '1000',
  'One delivered image charges one point and releases the unused image hold after admission pauses');
$before = snapshot(); $images->reconcile();
check(snapshot() === $before, 'Trial image reconciliation cannot charge twice');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Trial image settlement preserves shared ledger reconstruction');
$trialConfig->getEditable('xinshi_ai_billing.settings')->set('enabled', TRUE)->save();

$clock = new FixtureClock(time()); $context = \Drupal::service('xinshi_credits.context');
$uuid = \Drupal::service('uuid'); $entities = \Drupal::entityTypeManager();
$budgets = new \Drupal\xinshi_credits\CreditBudgets($db, $context, $clock);
$store = new \Drupal\xinshi_credits\CreditStore($db, $context, $clock, $uuid);
$datedLife = new \Drupal\xinshi_credits\CreditLifecycle($db, $context, $store, $budgets, $uuid, $entities);
$datedLedger = new \Drupal\xinshi_credits\CreditLedger($db, $context, $uuid, $entities, $budgets, $store);
$datedUser = User::create(['name' => 'trial-dated', 'status' => 1]); $datedUser->save();
$poolId = $trialConfig->get('xinshi_rewards.settings')->get('points_pool_ids')[0];
$grant = $datedLedger->grant(['beneficiary_user_id' => (string) $datedUser->id(), 'actor_user_id' => $uid,
  'amount_atoms' => '20000', 'source_kind' => 'promotion', 'source_ref' => 'synthetic-dated', 'grant_kind' => 'G13',
  'rule_id' => 'G13', 'rule_version' => 'trial-fixture-v2', 'budget_pool_id' => $poolId, 'scope' => ['chat', 'image'],
  'expires_at' => $clock->now + 5, 'public_reason' => 'Synthetic expiry'], 'trial-dated-grant');
$datedCommon = ['beneficiary_user_id' => (string) $datedUser->id(), 'actor_user_id' => $uid, 'public_reason' => 'Synthetic expiry'];
$datedLife->reserve($datedCommon + ['operation_id' => 'trial-dated-operation', 'quote_id' => 'dated-q',
  'authorization_version' => 'v1', 'quote_expires_at' => $clock->now + 60, 'category' => 'chat',
  'amount_atoms' => '5000', 'accounting_mode' => 'points'], 'trial-dated-hold');
$clock->now += 10;
$closed = $datedLife->settle($datedCommon + ['operation_id' => 'trial-dated-operation', 'outcome' => 'completed',
  'evidence_ref' => 'synthetic-dated', 'charged_atoms' => '2000', 'accounting_mode' => 'points'], 'trial-dated-close');
check($closed['released_atoms'] === '0' && $closed['expired_atoms'] === '3000', 'Expired trial credits settle the delivery and expire unused held points');
$returned = $datedLife->returnCredits($datedCommon + ['operation_id' => 'trial-dated-operation', 'return_ref' => 'dated-return',
  'returns' => [['grant_id' => $grant['grant_id'], 'amount_atoms' => '2000']]], 'trial-dated-return');
check($returned['expired_atoms'] === '2000', 'Returns do not reactivate expired trial points');
$datedLife->expire($datedCommon + ['grant_id' => $grant['grant_id']], 'trial-dated-expire');
check(\Drupal::service('xinshi_credits.audit')->inspect((string) $datedUser->id())['status'] === 'complete', 'Dated trial grants, late settlement, returns and expiry reconstruct exactly');
// Persist only synthetic inputs for the separate HTTP and image fixtures.
file_put_contents($fixture . '/trial.json', json_encode(['uid' => $uid, 'admin' => (string) $admin->id()]));
echo 'Trial checks: ' . $checks . PHP_EOL;
\Drupal::service('kernel')->invalidateContainer();
