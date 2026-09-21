<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use Psr\Log\LoggerInterface;

/**
 * Records usage facts for model calls this Drupal installation makes itself (UB2.4).
 *
 * The Node chat service delivers its events over the signed ingest; Drupal's
 * own image jobs already run inside the fact store, so their events go through
 * the same UsageIngestService directly (same contract validation, same
 * deduplication, same projection consumer) under the producer id `cms-image`.
 *
 * Rules mirror the Node metering store: the intent is durable before the
 * provider request is issued; every observation is a new monotonic revision;
 * an intent left open by an earlier run becomes `unknown`, never free and never
 * silently retried. Deliveries are separate append-only facts keyed by
 * (attempt, output index, artifact kind), so the supplier-billed image count
 * and the count the user actually received never merge into one number.
 */
class LocalUsageProducer {

  public const PRODUCER_ID = 'cms-image';
  public const DELIVERY_TABLE = 'ai_usage_delivery';
  public const MODE_SETTING = 'xinshi_ai_usage.mode';
  public const MODE_OBSERVE = 'observe';
  public const MODE_ENFORCE = 'enforce';
  public const STATE_PERSISTED = 'persisted';
  public const STATE_COMMITTED = 'committed';
  public const INTERRUPTED = 'process_interrupted';

  public function __construct(
    private readonly Connection $database,
    private readonly UsageIngestService $ingest,
    private readonly ProducerVault $vault,
    private readonly Settings $settings,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * `observe` keeps serving jobs when a fact cannot be written; `enforce` refuses the call.
   */
  public function mode(): string {
    $mode = $this->settings->get(self::MODE_SETTING, self::MODE_OBSERVE);
    if ($mode !== self::MODE_ENFORCE && $mode !== self::MODE_OBSERVE) {
      $this->logger->error('Invalid @setting "@mode"; falling back to observe', [
        '@setting' => self::MODE_SETTING, '@mode' => is_scalar($mode) ? (string) $mode : gettype($mode),
      ]);
      return self::MODE_OBSERVE;
    }
    return $mode;
  }

  /**
   * The site id local facts are recorded under, or NULL when none is configured.
   *
   * No request host fallback: a CLI queue worker and a web request would
   * otherwise split one site's facts across two ids that no price book matches.
   */
  public function siteId(): ?string {
    $sites = RegisteredSites::list($this->settings, $this->vault);
    if (count($sites) > 1) {
      $this->logger->warning('Several producer site ids are registered (@sites); local usage uses @site. Set @setting to choose explicitly.', [
        '@sites' => implode(', ', $sites), '@site' => $sites[0], '@setting' => RegisteredSites::SITE_SETTING,
      ]);
    }
    return $sites[0] ?? NULL;
  }

  /**
   * Persists the intent of one provider request before it is issued.
   *
   * @param array $intent
   *   operation_id, logical_call_id, feature, stage, payer, billing_role,
   *   provider_account_ref, requested_model, actor_user_id (nullable),
   *   task_id (nullable), started_at (ms, optional).
   *
   * @return array{attempt_id:string,attempt_no:int,site_id:string,started_at:int}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\LocalUsageException
   */
  public function prepare(array $intent): array {
    $siteId = $this->siteId();
    if ($siteId === NULL) {
      throw new LocalUsageException('site_unresolved',
        'No usage site id: register a producer or set ' . RegisteredSites::SITE_SETTING);
    }
    $operationId = (string) $intent['operation_id'];
    $logicalCallId = (string) $intent['logical_call_id'];
    // The prepared events of a logical call number its attempts, so a job that
    // is run again continues the sequence instead of starting a new call.
    $attemptNo = $this->preparedCount($siteId, $operationId, $logicalCallId) + 1;
    $attemptId = 'att_' . $this->uuid->generate();
    $startedAt = (int) ($intent['started_at'] ?? $this->nowMs());
    $event = [
      'schema_version' => UsageEventValidator::SCHEMA_VERSION,
      'event_id' => "$attemptId:prepared:0",
      'producer_id' => self::PRODUCER_ID,
      'site_id' => $siteId,
      'event_type' => 'attempt.prepared',
      'operation_id' => $operationId,
      'authorization_id' => NULL,
      'attempt_id' => $attemptId,
      'logical_call_id' => $logicalCallId,
      'observation_revision' => 0,
      'occurred_at' => UsageEventValidator::fromMilliseconds($startedAt),
      'context' => [
        'billing_account_id' => NULL,
        'actor_user_id' => $intent['actor_user_id'] ?? NULL,
        'feature' => (string) $intent['feature'],
        'stage' => (string) $intent['stage'],
        'attempt_no' => $attemptNo,
        'payer' => (string) $intent['payer'],
        'billing_role' => (string) $intent['billing_role'],
        'chat_run_id' => NULL,
        'task_id' => $intent['task_id'] ?? NULL,
        'chat_id' => NULL,
      ],
      'provider' => [
        'account_ref' => (string) $intent['provider_account_ref'],
        'requested_model' => (string) $intent['requested_model'],
        'resolved_model' => NULL,
        'gateway_request_id' => NULL,
        'provider_request_id' => NULL,
      ],
      'dispatch_state' => 'not_sent',
      'outcome' => NULL,
      'error_code' => NULL,
      'usage' => NULL,
      'timing' => [
        'started_at' => UsageEventValidator::fromMilliseconds($startedAt),
        'first_token_at' => NULL,
        'finished_at' => NULL,
      ],
    ];
    $this->write($event, $siteId);
    return ['attempt_id' => $attemptId, 'attempt_no' => $attemptNo, 'site_id' => $siteId,
      'started_at' => $startedAt];
  }

  /**
   * Appends an observation of a prepared attempt as the next revision.
   *
   * @param array $observation
   *   outcome, dispatch_state, usage (contract block or NULL), error_code,
   *   resolved_model, gateway_request_id, provider_request_id, finished_at (ms).
   *
   * @return array{attempt_id:string,observation_revision:int}
   *
   * @throws \Drupal\xinshi_ai_usage\Service\LocalUsageException
   */
  public function observe(string $attemptId, array $observation): array {
    $prepared = $this->preparedEvent($attemptId);
    if ($prepared === NULL) {
      throw new LocalUsageException('unknown_attempt', "No prepared event for attempt $attemptId");
    }
    $siteId = (string) $prepared['site_id'];
    $revision = $this->maxObservedRevision($siteId, $attemptId) + 1;
    $finishedAt = (int) ($observation['finished_at'] ?? $this->nowMs());
    $event = $prepared;
    $event['event_id'] = "$attemptId:observed:$revision";
    $event['event_type'] = 'attempt.observed';
    $event['observation_revision'] = $revision;
    $event['occurred_at'] = UsageEventValidator::fromMilliseconds($finishedAt);
    $event['provider']['resolved_model'] = $observation['resolved_model'] ?? NULL;
    $event['provider']['gateway_request_id'] = $observation['gateway_request_id'] ?? NULL;
    $event['provider']['provider_request_id'] = $observation['provider_request_id'] ?? NULL;
    $event['dispatch_state'] = (string) $observation['dispatch_state'];
    $event['outcome'] = (string) $observation['outcome'];
    $event['error_code'] = $observation['error_code'] ?? NULL;
    $event['usage'] = $observation['usage'] ?? NULL;
    $event['timing']['finished_at'] = UsageEventValidator::fromMilliseconds($finishedAt);
    $this->write($event, $siteId);
    return ['attempt_id' => $attemptId, 'observation_revision' => $revision];
  }

  /**
   * Marks intents of an operation that never got an observation as closed.
   *
   * Called before a job runs again or when a dead worker's job is recovered:
   * a previous worker may have died between dispatch and observation, and the
   * upstream may have charged for that request. A late observation from the
   * earlier worker still lands as a higher revision, so nothing is lost
   * either way.
   *
   * @param string $dispatchState
   *   What the caller knows about the request of the open intent: `unknown`
   *   (default) when it is not known whether it left the process; `sent` when
   *   the provider answered but the outcome was never observed (UB2.5 lease
   *   row with a response mark); `not_sent` when there is evidence it never
   *   left (lease row without a dispatch mark). The first two become
   *   `unknown` attempts that wait for reconciliation; the last becomes
   *   `not_sent`, no cost is expected and the job may run again.
   *
   * @return list<string>
   *   The attempt ids that were closed.
   */
  public function interruptOpenAttempts(string $operationId, string $dispatchState = 'unknown'): array {
    $siteId = $this->siteId();
    if ($siteId === NULL) {
      return [];
    }
    if (!in_array($dispatchState, ['unknown', 'sent', 'not_sent'], TRUE)) {
      throw new LocalUsageException('invalid_field', "Unsupported dispatch state $dispatchState");
    }
    $notSent = $dispatchState === 'not_sent';
    $observed = $this->database->select(UsageIngestService::TABLE, 'o')
      ->fields('o', ['attempt_id'])
      ->condition('o.site_id', $siteId)
      ->condition('o.producer_id', self::PRODUCER_ID)
      ->condition('o.operation_id', $operationId)
      ->condition('o.event_type', 'attempt.observed');
    $query = $this->database->select(UsageIngestService::TABLE, 'p')
      ->fields('p', ['attempt_id'])
      ->condition('p.site_id', $siteId)
      ->condition('p.producer_id', self::PRODUCER_ID)
      ->condition('p.operation_id', $operationId)
      ->condition('p.event_type', 'attempt.prepared')
      ->condition('p.attempt_id', $observed, 'NOT IN')
      ->orderBy('p.id');
    $interrupted = [];
    foreach ($query->execute()->fetchCol() as $attemptId) {
      $this->observe((string) $attemptId, [
        'outcome' => $notSent ? 'not_sent' : 'unknown',
        'dispatch_state' => $dispatchState,
        'usage' => NULL,
        'error_code' => self::INTERRUPTED,
      ]);
      $interrupted[] = (string) $attemptId;
    }
    return $interrupted;
  }

  /**
   * The prepared event of a local attempt, or NULL for attempts of other producers.
   *
   * @return array<string,mixed>|null
   */
  public function prepared(string $attemptId): ?array {
    return $this->preparedEvent($attemptId);
  }

  /**
   * The highest observation revision of a local attempt, or NULL while it is only prepared.
   *
   * @return array<string,mixed>|null
   */
  public function latestObservation(string $attemptId): ?array {
    $json = $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['payload_json'])
      ->condition('e.producer_id', self::PRODUCER_ID)
      ->condition('e.attempt_id', $attemptId)
      ->condition('e.event_type', 'attempt.observed')
      ->orderBy('e.observation_revision', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $decoded = $json === FALSE ? NULL : json_decode((string) $json, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  /**
   * Records one persisted or committed artifact of an attempt; replays are no-ops.
   */
  public function recordDelivery(string $siteId, string $operationId, string $attemptId, int $outputIndex,
    string $artifactKind, string $artifactRef, ?string $artifactVersion, string $state): void {
    try {
      $this->database->insert(self::DELIVERY_TABLE)->fields([
        'site_id' => $siteId,
        'operation_id' => $operationId,
        'attempt_id' => $attemptId,
        'output_index' => $outputIndex,
        'artifact_kind' => $artifactKind,
        'artifact_ref' => $artifactRef,
        'artifact_version' => $artifactVersion,
        'state' => $state,
        'delivered_at' => $this->nowMs(),
      ])->execute();
    }
    catch (IntegrityConstraintViolationException) {
      // The same artifact of the same attempt was already recorded.
    }
  }

  /**
   * @return list<array<string,mixed>>
   */
  public function deliveries(string $operationId): array {
    $siteId = $this->siteId();
    if ($siteId === NULL) {
      return [];
    }
    return $this->database->select(self::DELIVERY_TABLE, 'd')
      ->fields('d')
      ->condition('d.site_id', $siteId)
      ->condition('d.operation_id', $operationId)
      ->orderBy('d.attempt_id')
      ->orderBy('d.output_index')
      ->orderBy('d.id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  private function write(array $event, string $siteId): void {
    $receipt = $this->ingest->ingest([$event], self::PRODUCER_ID, $siteId)[0];
    if ($receipt['status'] === 'rejected') {
      throw new LocalUsageException($receipt['code'] ?? 'rejected',
        'Local usage event ' . $event['event_id'] . ' rejected: ' . ($receipt['code'] ?? 'rejected'));
    }
  }

  private function preparedCount(string $siteId, string $operationId, string $logicalCallId): int {
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->condition('e.site_id', $siteId)
      ->condition('e.producer_id', self::PRODUCER_ID)
      ->condition('e.operation_id', $operationId)
      ->condition('e.event_type', 'attempt.prepared');
    $count = 0;
    foreach ($query->fields('e', ['payload_json'])->execute()->fetchCol() as $json) {
      $decoded = json_decode((string) $json, TRUE);
      if (($decoded['logical_call_id'] ?? NULL) === $logicalCallId) {
        $count++;
      }
    }
    return $count;
  }

  private function preparedEvent(string $attemptId): ?array {
    $json = $this->database->select(UsageIngestService::TABLE, 'e')
      ->fields('e', ['payload_json'])
      ->condition('e.producer_id', self::PRODUCER_ID)
      ->condition('e.attempt_id', $attemptId)
      ->condition('e.event_type', 'attempt.prepared')
      ->execute()
      ->fetchField();
    $decoded = $json === FALSE ? NULL : json_decode((string) $json, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  private function maxObservedRevision(string $siteId, string $attemptId): int {
    $query = $this->database->select(UsageIngestService::TABLE, 'e')
      ->condition('e.site_id', $siteId)
      ->condition('e.attempt_id', $attemptId)
      ->condition('e.event_type', 'attempt.observed');
    $query->addExpression('MAX(e.observation_revision)', 'revision');
    return (int) $query->execute()->fetchField();
  }

  private function nowMs(): int {
    return (int) round($this->time->getCurrentMicroTime() * 1000);
  }

}
