<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Core\Database\Connection;
use Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer;
use Psr\Log\LoggerInterface;

/**
 * Operator entry for local attempts whose provider outcome is unknown (UB2.5).
 *
 * Counterpart of the Node `/chat/metering/attempts/:id/reconcile` route for
 * the `cms-image` producer: the operator's verdict becomes the next
 * observation revision of the attempt, so the projection, the cost rating and
 * the rollups update through the ordinary consumer. Nothing is edited in
 * place, and outcomes the provider itself reported are never rewritten.
 */
final class AttemptReconcileService {

  public const OUTCOMES = ['succeeded', 'failed', 'not_sent'];
  public const ERROR_CODE = 'manual_reconciliation';
  public const MAX_LIMIT = 200;
  /** Projected states an operator may reconcile. */
  private const RECONCILABLE = ['unknown', 'aborted'];

  public function __construct(
    private readonly Connection $database,
    private readonly LocalUsageProducer $producer,
    private readonly UsageProjectionService $projection,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Lists projected attempts in a state, most recently updated first.
   *
   * Reads the projection, so events the consumer has not applied yet are not
   * listed; run the process command first when the list looks incomplete.
   *
   * @return list<array<string,mixed>>
   */
  public function attempts(?string $siteId, string $state = 'unknown', int $limit = 50,
    ?string $producerId = NULL): array {
    $query = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a', ['site_id', 'attempt_id', 'operation_id', 'producer_id', 'feature', 'logical_call_id',
        'attempt_no', 'state', 'dispatch_state', 'error_code', 'requested_model', 'resolved_model',
        'gateway_request_id', 'images_generated', 'started_at', 'updated_at'])
      ->condition('a.state', $state)
      ->orderBy('a.updated_at', 'DESC')
      ->orderBy('a.id', 'DESC')
      ->range(0, min(max(1, $limit), self::MAX_LIMIT));
    if ($siteId !== NULL) {
      $query->condition('a.site_id', $siteId);
    }
    if ($producerId !== NULL) {
      $query->condition('a.producer_id', $producerId);
    }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Records the operator's verdict as a new observation of an unknown local attempt.
   *
   * @param string $attemptId
   *   An attempt of the local image producer.
   * @param array $verdict
   *   `outcome` (succeeded, failed or not_sent). A succeeded verdict may carry
   *   `images` (the number the supplier billed) and / or `usage` (the raw
   *   supplier response or usage object, normalized like a live response);
   *   optional `resolved_model`, `gateway_request_id` and
   *   `provider_request_id` replace the values recorded so far. `note` is
   *   logged with the verdict and never enters the event.
   *
   * @return array{attempt_id:string,observation_revision:int,projection:array<string,mixed>|null}
   *   The revision written and the projection row after the consumer ran
   *   (NULL while another consumer holds the projection lock).
   *
   * @throws \Drupal\xinshi_ai_usage\Service\LocalUsageException
   *   `invalid_verdict` for an unusable verdict, `unknown_attempt` when the
   *   attempt is not a local one, `not_unknown` when its current outcome was
   *   reported by the provider or the attempt is still open.
   */
  public function reconcile(string $attemptId, array $verdict): array {
    $outcome = (string) ($verdict['outcome'] ?? '');
    if (!in_array($outcome, self::OUTCOMES, TRUE)) {
      throw new LocalUsageException('invalid_verdict', 'outcome must be one of ' . implode(', ', self::OUTCOMES));
    }
    $hasUsage = isset($verdict['images']) || isset($verdict['usage']);
    if ($outcome !== 'succeeded' && $hasUsage) {
      throw new LocalUsageException('invalid_verdict', 'only a succeeded verdict carries images or usage');
    }
    if (isset($verdict['images']) && (!is_int($verdict['images']) || $verdict['images'] < 0)) {
      throw new LocalUsageException('invalid_verdict', 'images must be a non-negative integer');
    }
    if (isset($verdict['usage']) && !is_array($verdict['usage'])) {
      throw new LocalUsageException('invalid_verdict', 'usage must be a decoded JSON object');
    }
    if ($this->producer->prepared($attemptId) === NULL) {
      throw new LocalUsageException('unknown_attempt',
        "$attemptId is not an attempt of the local image producer; Node attempts are reconciled on the Node side");
    }
    $latest = $this->producer->latestObservation($attemptId);
    $current = $latest['outcome'] ?? NULL;
    if ($latest === NULL || !in_array($current, self::RECONCILABLE, TRUE)) {
      throw new LocalUsageException('not_unknown', sprintf(
        'Attempt %s is %s, not unknown; outcomes the provider reported are not rewritten.',
        $attemptId, $latest === NULL ? 'still open' : (string) $current));
    }

    $usage = NULL;
    if ($outcome === 'succeeded') {
      $raw = $verdict['usage'] ?? [];
      if (isset($verdict['images'])) {
        // The supplier's billed count is the image unit of the contract; it is
        // recorded like a response whose data list has that many entries.
        $raw['data'] = array_fill(0, $verdict['images'], []);
      }
      $usage = ImageUsageNormalizer::normalize($raw);
    }
    $provider = $latest['provider'] ?? [];
    $observation = [
      'outcome' => $outcome,
      'dispatch_state' => $outcome === 'not_sent' ? 'not_sent' : 'sent',
      'usage' => $usage,
      'error_code' => $outcome === 'succeeded' ? NULL : self::ERROR_CODE,
      'resolved_model' => self::text($verdict, 'resolved_model') ?? ($provider['resolved_model'] ?? NULL),
      'gateway_request_id' => self::text($verdict, 'gateway_request_id') ?? ($provider['gateway_request_id'] ?? NULL),
      'provider_request_id' => self::text($verdict, 'provider_request_id') ?? ($provider['provider_request_id'] ?? NULL),
    ];
    $written = $this->producer->observe($attemptId, $observation);
    $siteId = (string) $latest['site_id'];
    $this->logger->notice('Attempt @attempt (@operation) reconciled as @outcome, revision @revision@images@note', [
      '@attempt' => $attemptId,
      '@operation' => (string) ($latest['operation_id'] ?? ''),
      '@outcome' => $outcome,
      '@revision' => (string) $written['observation_revision'],
      '@images' => isset($verdict['images']) ? ', images ' . $verdict['images'] : '',
      '@note' => self::text($verdict, 'note') === NULL ? '' : ': ' . $verdict['note'],
    ]);
    $stats = $this->projection->process($siteId);
    return [
      'attempt_id' => $attemptId,
      'observation_revision' => $written['observation_revision'],
      'projection' => $stats['locked'] ? NULL : $this->projected($siteId, $attemptId),
    ];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function projected(string $siteId, string $attemptId): ?array {
    $row = $this->database->select(UsageProjectionService::ATTEMPT_TABLE, 'a')
      ->fields('a', ['state', 'dispatch_state', 'error_code', 'usage_revision', 'usage_quality',
        'images_generated', 'cost_valuation_state', 'cost_unpriced_reason', 'cost_total_micros'])
      ->condition('a.site_id', $siteId)
      ->condition('a.attempt_id', $attemptId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  private static function text(array $verdict, string $key): ?string {
    $value = $verdict[$key] ?? NULL;
    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

}
