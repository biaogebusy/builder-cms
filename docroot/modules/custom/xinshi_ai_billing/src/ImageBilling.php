<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Service\{ImageJobRuns, PromptRewriteClient};
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_credits\{CreditContext, CreditException, CreditValue};

/** Optional image adapter; no image modules or provider calls are needed by the ledger. */
final class ImageBilling {

  public function __construct(
    private readonly AiBilling $billing,
    private readonly BillingProfiles $profiles,
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditContext $context,
    private readonly EntityTypeManagerInterface $entities,
    private readonly LocalUsageProducer $usage,
    private readonly TimeInterface $time,
    private readonly ?PromptRewriteClient $rewrite = NULL,
    private readonly ?ImageJobRuns $runs = NULL,
  ) {}

  /** Quote the exact effective request; no authorization or job is created here. */
  public function quote(array $input, string $key): array {
    if (!$this->enabled()) return ['status' => 'disabled'];
    if (($input['platform'] ?? '') === 'custom') return ['status' => 'customer_key'];
    $profile = $this->profiles->select((string) $input['jobKind'], (string) $input['model'], (string) $input['platform']);
    $this->validate($input, $profile);
    return ['status' => 'quoted', 'quote' => $this->billing->quote([
      'feature' => $input['jobKind'], 'model' => $input['model'], 'platform' => $input['platform'],
      'request_hash' => self::requestHash($input), 'producer_id' => LocalUsageProducer::PRODUCER_ID,
    ], $key)];
  }

  /** One quote creates one job and one hold, even when the submit response is lost. */
  public function create(array $input, mixed $quoteId, callable $create): NodeInterface {
    if (!$quoteId && (!$this->enabled() || ($input['platform'] ?? '') === 'custom')) return $create();
    $quoteId = CreditValue::id($quoteId);
    $quote = $this->database->select('ai_billing_quote', 'q')->fields('q')
      ->condition('site_id', $this->context->site())->condition('id', $quoteId)->execute()->fetchAssoc();
    if (!$quote || $quote['producer_id'] !== LocalUsageProducer::PRODUCER_ID) throw new CreditException('quote_unavailable', 404);
    $this->validate($input, $this->profiles->load($quote['version']));
    $existing = $this->database->select('ai_billing_authorization', 'a')->fields('a', ['operation_id'])
      ->condition('site_id', $this->context->site())->condition('quote_id', $quoteId)->execute()->fetchField();
    $job = NULL;
    $operation = $existing ?: $quoteId;
    // AiBilling retains the scoped principal and checks quote ownership and the request hash.
    $this->billing->authorize(['operation_id' => $operation, 'quote_id' => $quoteId,
      'request_hash' => self::requestHash($input)], function () use ($create, $operation, &$job): void {
        $job = $create($operation);
        if ($job->uuid() !== $operation) throw new \LogicException('Image operation binding changed.');
      });
    if (!$job) {
      $found = $this->entities->getStorage('node')->loadByProperties(['uuid' => $operation, 'type' => 'image_job']);
      $job = reset($found);
      if (!$job) throw new CreditException('operation_unavailable', 409);
    }
    return $job;
  }

  /** Run before prompt rewriting and before opening any provider intent. */
  public function beforeRun(NodeInterface $job, array $generation): void {
    $auth = $this->authorization($job->uuid());
    if (!$auth && (!$this->enabled() || $job->get('field_platform')->value === 'custom')) return;
    if (!$auth || $auth['state'] !== 'open' || $auth['user_id'] !== (string) $job->getOwnerId()) {
      throw new CreditException('image_authorization_required', 403);
    }
    $owner = $this->entities->getStorage('user')->load($auth['user_id']);
    if (!$owner || !$owner->isActive() || !$owner->hasPermission('use ai credits')) {
      throw new CreditException('account_unavailable', 403);
    }
    $quote = $this->database->select('ai_billing_quote', 'q')->fields('q')
      ->condition('id', $auth['quote_id'])->execute()->fetchAssoc();
    $profile = $this->profiles->load($quote['version']);
    $input = self::jobInput($job);
    $this->validate($input, $profile);
    if (!hash_equals($quote['request_hash'], self::requestHash($input))) throw new CreditException('quote_mismatch', 409);
    $expected = $profile['image']['params'] + ['user' => $auth['user_id']];
    if ($input['negativePrompt'] !== '') $expected['negative_prompt'] = $input['negativePrompt'];
    if (BillingProfiles::json($generation) !== BillingProfiles::json($expected)) throw new CreditException('image_parameters_changed', 409);
    // A billed attempt is never retried implicitly. Unknown calls retain the original hold.
    if ($this->attempts($job->uuid())) throw new CreditException('image_execution_unresolved', 409);
  }

  /** Claim the actual model/account permit after durable metering and before provider I/O. */
  public function permit(array $call, string $attemptId): void {
    $auth = $this->authorization($call['operation_id']);
    if (!$auth && (!$this->enabled() || $call['platform'] === 'custom')) return;
    if (!$auth) throw new CreditException('image_authorization_required', 403);
    $this->billing->permit($this->producer(), ['operation_id' => $call['operation_id'],
      'attempt_id' => $attemptId, 'model' => $call['model'], 'account_ref' => $call['platform'], 'role' => 'primary']);
  }

  /** Retry only financial finalization; a live or unrecovered worker cannot close its manifest. */
  public function reconcile(): void {
    if (!$this->runs) return;
    $rows = $this->database->select('ai_billing_authorization', 'a')->fields('a')
      ->condition('site_id', $this->context->site())->condition('producer_id', LocalUsageProducer::PRODUCER_ID)
      ->condition('state', ['open', 'awaiting_evidence'], 'IN')
      ->orderBy('checked_at')->orderBy('created_at')->orderBy('operation_id')->range(0, 100)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $auth) {
      try {
        // Rotate unfinished and failed entries too, so they cannot block later settlements.
        $this->database->update('ai_billing_authorization')->fields(['checked_at' => $this->time->getCurrentTime()])
          ->condition('site_id', $auth['site_id'])->condition('operation_id', $auth['operation_id'])->execute();
        $operation = $auth['operation_id'];
        $run = $this->runs->row($operation);
        if ($run && $run['state'] !== ImageJobRuns::STATE_FINISHED) continue;
        if ($auth['final_json'] !== '') {
          $final = json_decode($auth['final_json'], TRUE, 16, JSON_THROW_ON_ERROR);
        }
        else {
          $jobs = $this->entities->getStorage('node')->loadByProperties(['uuid' => $operation, 'type' => 'image_job']);
          $job = reset($jobs);
          if (!$job || !in_array($job->get('field_status')->value, ['succeeded', 'failed', 'cancelled'], TRUE)) continue;
          $final = ['delivery' => (int) $job->get('field_n_succeeded')->value > 0 ? 'delivered' : 'none',
            'delivery_ref' => 'image-job:' . $operation . ':' . $job->getRevisionId(),
            'delivery_count' => (int) $job->get('field_n_succeeded')->value];
        }
        $attempts = array_map(static fn(array $row): array => ['attempt_id' => $row['attempt_id'],
          'revision' => (int) $row['usage_revision']], $this->attempts($operation));
        $this->billing->finalize($this->producer(), ['operation_id' => $operation,
          'delivery' => $final['delivery'], 'delivery_ref' => $final['delivery_ref'],
          'delivery_count' => $final['delivery_count'], 'attempts' => $attempts]);
      }
      catch (\Throwable) {
        // Persistent authorizations remain pending; cron never dispatches or repeats models.
      }
    }
  }

  /** Keep the source locator while execution or financial evidence is unresolved. */
  public function assertDeletable(string $operation): void {
    $auth = $this->authorization($operation);
    if ($auth && $auth['state'] !== 'closed') throw new CreditException('image_billing_pending', 409);
  }

  private function validate(array $input, array $profile): void {
    if ($this->usage->mode() !== LocalUsageProducer::MODE_ENFORCE || $this->usage->siteId() !== $this->context->site()) {
      throw new CreditException('image_metering_unavailable', 503);
    }
    if ($profile['category'] !== 'image' || !isset($profile['image'])
      || $profile['feature'] !== $input['jobKind'] || $profile['model'] !== $input['model']
      || $profile['platform'] !== $input['platform']) throw new CreditException('image_price_unavailable', 503);
    $request = self::request($input);
    if (strlen($request['prompt']) + strlen($request['negativePrompt']) > $profile['image']['max_prompt_bytes']
      || BillingProfiles::json($request['params']) !== BillingProfiles::json($profile['image']['params'])) {
      throw new CreditException('image_request_outside_quote', 422);
    }
    // This price version admits one image call; auxiliary live-data rewrites need their own policy.
    if ($input['jobKind'] === 'text_to_image' && $this->rewrite?->isConfigured()
      && PromptRewriteClient::needsRewrite($request['prompt'])) throw new CreditException('image_rewrite_not_quoted', 422);
  }

  /** Deterministic request identity without credentials or task-group metadata. */
  public static function requestHash(array $input): string { return hash('sha256', BillingProfiles::json(self::request($input))); }

  private static function request(array $input): array {
    foreach (['jobKind', 'platform', 'model'] as $field) CreditValue::text($input[$field] ?? NULL, 128);
    CreditValue::text($input['prompt'] ?? NULL, 100000);
    if (isset($input['negativePrompt']) && $input['negativePrompt'] !== '') CreditValue::text($input['negativePrompt'], 100000);
    if (isset($input['inputImage'])) CreditValue::id($input['inputImage']);
    $params = $input['params'] ?? [];
    if (!is_array($params)) throw new CreditException('invalid_image_request');
    // Input versions are resolved and checked by the existing image source boundary.
    unset($params['inputVersions']);
    $params['n'] = $input['nRequested'] ?? $params['n'] ?? 4;
    if (isset($params['size']) && is_string($params['size'])) $params['size'] = str_replace('*', 'x', $params['size']);
    return ['jobKind' => $input['jobKind'], 'platform' => $input['platform'], 'model' => $input['model'],
      'prompt' => $input['prompt'], 'negativePrompt' => $input['negativePrompt'] ?? '', 'params' => $params,
      'inputImage' => $input['inputImage'] ?? NULL];
  }

  private static function jobInput(NodeInterface $job): array {
    return ['jobKind' => (string) $job->get('field_job_kind')->value,
      'platform' => (string) $job->get('field_platform')->value, 'model' => (string) $job->get('field_model')->value,
      'prompt' => (string) $job->get('field_prompt')->value,
      'negativePrompt' => (string) $job->get('field_negative_prompt')->value,
      'nRequested' => (int) $job->get('field_n_requested')->value,
      'params' => json_decode((string) $job->get('field_params')->value, TRUE) ?: [],
      'inputImage' => $job->get('field_input_image')->entity?->uuid()];
  }

  private function authorization(string $operation): ?array {
    return $this->database->select('ai_billing_authorization', 'a')->fields('a')
      ->condition('site_id', $this->context->site())->condition('operation_id', $operation)->execute()->fetchAssoc() ?: NULL;
  }

  private function attempts(string $operation): array {
    // Use durable source events: the asynchronous report projection can lag behind dispatch.
    $query = $this->database->select('ai_usage_event', 'e')->fields('e', ['attempt_id'])
      ->condition('site_id', $this->context->site())->condition('operation_id', $operation)
      ->groupBy('attempt_id')->orderBy('attempt_id')->range(0, 129);
    $query->addExpression('MAX(observation_revision)', 'usage_revision');
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function enabled(): bool { return $this->config->get('xinshi_ai_billing.settings')->get('enabled') === TRUE; }
  private function producer(): array { return ['producer_id' => LocalUsageProducer::PRODUCER_ID, 'site_id' => $this->context->site()]; }

}
