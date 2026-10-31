<?php

declare(strict_types=1);

use Drupal\Core\Site\Settings;
use Drupal\field\Entity\{FieldConfig, FieldStorageConfig};
use Drupal\node\Entity\{Node, NodeType};
use Drupal\user\Entity\User;
use Drupal\xinshi_ai\Service\{ImageJobRuns, ImageUsageRecorder};
use Drupal\xinshi_ai_billing\ImageBilling;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer;

require __DIR__ . '/../../xinshi_credits/tests/bootstrap.php';
require __DIR__ . '/../../xinshi_credits/tests/fixtures.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, (string) $error . PHP_EOL); exit(1); });
$loader->addPsr4('Drupal\\xinshi_ai\\', __DIR__ . '/../../xinshi_ai/src');
require __DIR__ . '/../../xinshi_ai/xinshi_ai.install';

// Real entities and transactions, with no provider module, model or real site credentials.
\Drupal::service('module_installer')->install(['node', 'field']);
NodeType::create(['type' => 'image_job', 'name' => 'Synthetic image job'])->save();
foreach (['field_job_kind', 'field_platform', 'field_model', 'field_prompt',
  'field_negative_prompt', 'field_params', 'field_status', 'field_task_id',
  'field_n_requested', 'field_n_succeeded', 'field_input_image'] as $name) {
  $type = str_starts_with($name, 'field_n_') ? 'integer' : ($name === 'field_input_image' ? 'entity_reference' : 'string_long');
  $storage = ['entity_type' => 'node', 'field_name' => $name, 'type' => $type];
  if ($type === 'entity_reference') $storage['settings'] = ['target_type' => 'node'];
  FieldStorageConfig::create($storage)->save();
  FieldConfig::create(['entity_type' => 'node', 'bundle' => 'image_job', 'field_name' => $name])->save();
}
$db->schema()->createTable(ImageJobRuns::TABLE, xinshi_ai_schema()[ImageJobRuns::TABLE]);
$entities = \Drupal::entityTypeManager();
$users = $entities->getStorage('user')->loadByProperties(['name' => 'ai_credit_user']);
$user = reset($users); $uid = (string) $user->id();
\Drupal::currentUser()->setAccount($user);
$settings = \Drupal::configFactory()->getEditable('xinshi_ai_billing.settings');
$profiles = \Drupal::service('xinshi_ai_billing.profiles');
$billing = \Drupal::service('xinshi_ai_billing.billing');
$pool = pool('image-billing', '200000', '10000000');
$bounds = json_decode($db->select('credit_reward_pool', 'p')->fields('p', ['bounds_json'])
  ->condition('id', $pool)->execute()->fetchField(), TRUE);
\Drupal::service('xinshi_credits.ledger')->grant(command($pool, 'image-billing', 'image-gift', $uid, '100000'), 'image-gift');
$profile = ['version' => 'synthetic-image-v1', 'feature' => 'text_to_image', 'model' => 'fixture-image',
  'platform' => 'fixture', 'category' => 'image', 'currency' => 'CNY', 'cost_bucket_id' => $bounds['image']['bucket_id'],
  'evidence_ref' => 'SYNTHETIC ONLY', 'quote_ttl_seconds' => 300,
  'image' => ['max_prompt_bytes' => 1000, 'params' => ['n' => 2, 'size' => '1024x1024']],
  'calls' => [['model' => 'fixture-image', 'account_ref' => 'fixture', 'role' => 'primary', 'max_calls' => 1,
    'max_input_tokens' => 0, 'max_output_tokens' => 0, 'max_images' => 2, 'cost_limit_micros' => '500',
    'input_atoms_per_million' => '0', 'output_atoms_per_million' => '0', 'atoms_per_image' => '600']]];
$profiles->publish($profile);
$settings->set('enabled', TRUE)->set('active_versions', ['synthetic-image-v1'])->save();
$localSettings = new Settings(['xinshi_ai_usage.mode' => 'enforce', 'xinshi_ai_usage.site_id' => 'fixture']);
$usage = new LocalUsageProducer($db, \Drupal::service('xinshi_ai_usage.ingest'),
  \Drupal::service('xinshi_ai_usage.producer_vault'), $localSettings, \Drupal::time(),
  \Drupal::service('uuid'), \Drupal::logger('xinshi_ai_usage'));
$runs = new ImageJobRuns($db, \Drupal::time(), \Drupal::service('uuid'));
$images = new ImageBilling($billing, $profiles, $db, \Drupal::configFactory(),
  \Drupal::service('xinshi_credits.context'), $entities, $usage, \Drupal::time(), NULL, $runs);
$recorder = new ImageUsageRecorder($usage, \Drupal::logger('xinshi_ai_usage'), $images);
$input = ['jobKind' => 'text_to_image', 'platform' => 'fixture', 'model' => 'fixture-image',
  'prompt' => 'A synthetic image', 'negativePrompt' => '', 'params' => ['n' => 2, 'size' => '1024*1024']];
$created = 0;
$create = function (?string $operation = NULL) use (&$created, $input, $uid): Node {
  $created++;
  $node = Node::create(['type' => 'image_job', 'uuid' => $operation ?? \Drupal::service('uuid')->generate(), 'uid' => $uid, 'title' => 'Synthetic image',
    'field_job_kind' => $input['jobKind'], 'field_platform' => $input['platform'], 'field_model' => $input['model'],
    'field_prompt' => $input['prompt'], 'field_params' => json_encode($input['params']),
    'field_n_requested' => 2, 'field_n_succeeded' => 0, 'field_status' => 'queued']);
  $node->save(); return $node;
};
$settings->clear('image_enabled')->save();
$beforeIncluded = snapshot();
check($images->quote($input, 'included-no-flag') === ['status' => 'included'], 'Missing rollout flag keeps platform images included');
\Drupal::moduleHandler()->loadInclude('xinshi_ai_billing', 'install');
xinshi_ai_billing_update_11001(); xinshi_ai_billing_update_11001();
check(\Drupal::config('xinshi_ai_billing.settings')->get('image_enabled') === FALSE, 'Update is idempotent and defaults image charging off');
$included = $images->create($input, NULL, $create);
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->clear('site_id')->save();
$images->beforeRun($included, ['n' => 2, 'size' => '1024x1024', 'user' => $uid]);
$includedAttempt = $recorder->begin(['operation_id' => $included->uuid(), 'kind' => 'text_to_image',
  'platform' => 'fixture', 'model' => 'fixture-image', 'actor_user_id' => $uid, 'task_id' => NULL]);
check($includedAttempt !== NULL && snapshot() === $beforeIncluded, 'Included image creates a task and metering attempt without a financial write');
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')->save();
$settings->set('image_enabled', TRUE)->save();
xinshi_ai_billing_update_11001();
check(\Drupal::config('xinshi_ai_billing.settings')->get('image_enabled') === TRUE, 'Update preserves an explicit image opt-in');
$created = 0;
$quote = $images->quote($input, 'image-first')['quote'];
check($quote['maximum_atoms'] === '1200', 'Image quote covers the exact admitted image count');
denied(fn() => $billing->authorize(['quote_id' => $quote['quote_id'], 'operation_id' => $quote['quote_id'],
  'request_hash' => ImageBilling::requestHash($input)]), 'image_task_required');
$state = snapshot();
try {
  $images->create($input, $quote['quote_id'], function (string $operation) use ($create): never {
    $create($operation);
    throw new RuntimeException('Synthetic task save failure');
  });
  throw new RuntimeException('Expected task save failure');
} catch (RuntimeException $error) {
  check($error->getMessage() === 'Synthetic task save failure' && snapshot() === $state,
    'A task save failure rolls back its credit and cost reservation');
}
check(!$entities->getStorage('node')->loadByProperties(['uuid' => $quote['quote_id']]),
  'A rolled-back image authorization leaves no task entity');
$created = 0;
denied(fn() => $images->quote(array_replace($input, ['params' => ['n' => 3, 'size' => '1024x1024']]), 'too-many'), 'image_request_outside_quote');
$job = $images->create($input, $quote['quote_id'], $create);
$state = snapshot();
check($images->create($input, $quote['quote_id'], $create)->uuid() === $job->uuid()
  && $created === 1 && snapshot() === $state, 'Lost image response reuses the same job and hold');
denied(fn() => $images->create(array_replace($input, ['prompt' => 'Changed']), $quote['quote_id'], $create), 'quote_mismatch');
$other = User::create(['name' => 'image-credit-other', 'status' => 1, 'roles' => ['ai_credit_user']]); $other->save();
\Drupal::currentUser()->setAccount($other);
denied(fn() => $images->create($input, $quote['quote_id'], $create), 'quote_mismatch');
\Drupal::currentUser()->setAccount($user);
denied(fn() => $images->assertDeletable($job->uuid()), 'image_billing_pending');
$settings->set('image_enabled', FALSE)->save();
$generation = ['n' => 2, 'size' => '1024x1024', 'user' => $uid];
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->clear('site_id')->save();
denied(fn() => $images->beforeRun($job, $generation), 'credits_not_configured');
\Drupal::configFactory()->getEditable('xinshi_credits.settings')->set('site_id', 'fixture')->save();
$images->beforeRun($job, $generation);
denied(fn() => $images->beforeRun($job, array_replace($generation, ['n' => 3])), 'image_parameters_changed');
$run = $runs->claim($job->uuid(), 0);
$call = ['operation_id' => $job->uuid(), 'kind' => 'text_to_image', 'platform' => 'fixture',
  'model' => 'fixture-image', 'actor_user_id' => $uid, 'task_id' => NULL];
$attempt = $recorder->begin($call);
check($attempt !== NULL, 'Billed image opens a durable metering attempt and permit');
denied(fn() => $images->beforeRun($job, $generation), 'image_execution_unresolved');
$job->set('field_status', 'cancelled')->save();
$images->reconcile();
check($billing->own($job->uuid())['status'] === 'open', 'Cancellation cannot finalize while a worker still owns execution');
$run->release();
$images->reconcile();
check($billing->own($job->uuid())['status'] === 'awaiting_evidence', 'Unknown image execution retains the original hold');
\Drupal::service('xinshi_ai_usage.projection')->process('fixture');

// Supplier output counts and file persistence alone do not prove user delivery.
$usage->observe($attempt->attemptId, ['outcome' => 'succeeded', 'dispatch_state' => 'sent', 'usage' => ImageUsageNormalizer::normalize(['data' => [['url' => 'synthetic-a'], ['url' => 'synthetic-b']]]), 'error_code' => NULL]);
\Drupal::service('xinshi_ai_usage.projection')->process('fixture');
$db->insert('ai_usage_cost_entry')->fields(['site_id' => 'fixture', 'attempt_id' => $attempt->attemptId,
  'observation_revision' => 1, 'source_kind' => 'supplier_reconciliation',
  'valuation_state' => 'reconciled', 'currency' => 'CNY',
  'total_cost_micros' => 55, 'computed_at' => time()])->execute();
$attempt->persisted(0, 'synthetic-media');
$images->reconcile();
check($billing->own($job->uuid())['charged_atoms'] === '0', 'Undelivered provider images charge zero credits');
$state = snapshot(); $images->reconcile();
check(snapshot() === $state, 'Image reconciliation never repeats the settled transaction');
$images->assertDeletable($job->uuid());
$settings->set('image_enabled', TRUE)->save();

$quote2 = $images->quote($input, 'image-delivered')['quote'];
$job2 = $images->create($input, $quote2['quote_id'], $create);
$images->beforeRun($job2, $generation);
$attempt2 = $recorder->begin(array_replace($call, ['operation_id' => $job2->uuid()]));
$usage->observe($attempt2->attemptId, ['outcome' => 'succeeded', 'dispatch_state' => 'sent', 'usage' => ImageUsageNormalizer::normalize(['data' => [['url' => 'synthetic-a'], ['url' => 'synthetic-b']]]), 'error_code' => NULL]);
\Drupal::service('xinshi_ai_usage.projection')->process('fixture');
$job2->set('field_status', 'succeeded')->set('field_n_succeeded', 1)->save();
$images->reconcile();
check($billing->own($job2->uuid())['status'] === 'awaiting_evidence', 'Delivered images wait for authoritative cost evidence');
$db->insert('ai_usage_cost_entry')->fields(['site_id' => 'fixture', 'attempt_id' => $attempt2->attemptId,
  'observation_revision' => 1, 'source_kind' => 'supplier_reconciliation',
  'valuation_state' => 'reconciled', 'currency' => 'CNY',
  'total_cost_micros' => 55, 'computed_at' => time()])->execute();
$images->reconcile();
check($billing->own($job2->uuid())['status'] === 'awaiting_evidence', 'A job counter alone does not prove an image was committed');
$attempt2->committed(0, 'synthetic-image-asset');
$settings->set('enabled', FALSE)->save();
$images->reconcile();
$settled = $billing->own($job2->uuid());
check($settled['charged_atoms'] === '600' && $settled['released_atoms'] === '600',
  'One committed image charges once under the original price after admission is disabled');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Image operations preserve ledger and budget reconstruction');

// A full batch of unfinished jobs must not prevent later terminal jobs from closing.
$settings->set('enabled', TRUE)->save();
\Drupal::service('xinshi_credits.ledger')->grant(command($pool, 'image-billing', 'image-batch-gift', $uid, '100000'), 'image-batch-gift');
for ($i = 0; $i < 100; $i++) {
  $queued = $images->quote($input, 'image-queued-' . $i)['quote'];
  $images->create($input, $queued['quote_id'], $create);
}
$later = $images->quote($input, 'image-later-terminal')['quote'];
$laterJob = $images->create($input, $later['quote_id'], $create);
$laterJob->set('field_status', 'cancelled')->save();
// Fix the ordering independently of the execution speed of either database driver.
$db->update('ai_billing_authorization')->fields(['created_at' => time() + 60])
  ->condition('operation_id', $laterJob->uuid())->execute();
$images->reconcile();
check($billing->own($laterJob->uuid())['status'] === 'open', 'Image reconciliation remains bounded to one batch');
$images->reconcile();
check($billing->own($laterJob->uuid())['status'] === 'released', 'Pending image jobs cannot starve later terminal reconciliation');
check(\Drupal::service('xinshi_credits.audit')->inspect($uid)['status'] === 'complete', 'Batch reconciliation preserves all unfinished holds');
echo 'Image billing checks: ' . $checks . PHP_EOL;
