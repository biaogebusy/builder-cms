<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\xinshi_ai_usage\Contract\UsageContractException;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use Psr\Log\LoggerInterface;

/**
 * Persists usage events exactly once per (site, producer, event ID).
 *
 * Each event is committed on its own before its receipt is produced, so a
 * receipt of `accepted` or `duplicate` always describes a durable row. A
 * redelivery with the same content is a duplicate; the same ID with different
 * content is a conflict that is never overwritten.
 */
final class UsageIngestService {

  public const TABLE = 'ai_usage_event';
  public const MAX_EVENTS = 100;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Returns one receipt per input event, in input order.
   *
   * @param array $events
   *   Decoded JSON events as sent by the producer.
   * @param string $producerId
   *   The authenticated producer; events claiming another producer are rejected.
   * @param string $siteId
   *   The site the producer is registered for; other sites are rejected.
   *
   * @return array<int, array{event_id: string, status: string, code?: string}>
   */
  public function ingest(array $events, string $producerId, string $siteId): array {
    $receipts = [];
    foreach ($events as $raw) {
      $receipts[] = $this->ingestOne($raw, $producerId, $siteId);
    }
    return $receipts;
  }

  private function ingestOne(mixed $raw, string $producerId, string $siteId): array {
    $eventId = is_array($raw) && is_string($raw['event_id'] ?? NULL) ? $raw['event_id'] : '';
    try {
      $event = UsageEventValidator::validate($raw);
    }
    catch (UsageContractException $e) {
      $this->logger->warning('Rejected usage event @id from @producer: @code @message', [
        '@id' => $eventId, '@producer' => $producerId, '@code' => $e->contractCode, '@message' => $e->getMessage(),
      ]);
      return ['event_id' => $eventId, 'status' => 'rejected', 'code' => $e->contractCode];
    }
    if ($event['producer_id'] !== $producerId) {
      return ['event_id' => $event['event_id'], 'status' => 'rejected', 'code' => 'producer_mismatch'];
    }
    if ($event['site_id'] !== $siteId) {
      return ['event_id' => $event['event_id'], 'status' => 'rejected', 'code' => 'site_mismatch'];
    }
    // The stored hash covers the whole received object, so a redelivery from the
    // producer's outbox (identical JSON) matches and any edited payload does not.
    $hash = UsageEventValidator::payloadHash($raw);
    $existing = $this->existingHash($siteId, $producerId, $event['event_id']);
    if ($existing === NULL) {
      try {
        $this->database->insert(self::TABLE)->fields([
          'site_id' => $siteId,
          'producer_id' => $producerId,
          'event_id' => $event['event_id'],
          'event_type' => $event['event_type'],
          'schema_version' => $event['schema_version'],
          'operation_id' => $event['operation_id'],
          'attempt_id' => $event['attempt_id'],
          'observation_revision' => $event['observation_revision'],
          'payload_hash' => $hash,
          'occurred_at' => UsageEventValidator::toMilliseconds($event['occurred_at']),
          'received_at' => (int) round($this->time->getCurrentMicroTime() * 1000),
          'payload_json' => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ])->execute();
        return ['event_id' => $event['event_id'], 'status' => 'accepted'];
      }
      catch (IntegrityConstraintViolationException) {
        // A concurrent delivery won either the event-id or attempt-revision
        // uniqueness race; compare the durable row before returning a receipt.
        $existing = $this->existingHash($siteId, $producerId, $event['event_id']);
        if ($existing === NULL && $this->attemptRevisionExists(
          $siteId,
          $event['attempt_id'],
          $event['event_type'],
          $event['observation_revision'],
        )) {
          $this->logger->error('Usage attempt revision @attempt/@type/@revision arrived with a different event ID', [
            '@attempt' => $event['attempt_id'],
            '@type' => $event['event_type'],
            '@revision' => $event['observation_revision'],
          ]);
          return ['event_id' => $event['event_id'], 'status' => 'rejected', 'code' => 'attempt_revision_conflict'];
        }
      }
    }
    if ($existing === $hash) {
      return ['event_id' => $event['event_id'], 'status' => 'duplicate'];
    }
    $this->logger->error('Usage event @id from @producer arrived with different content; kept the original', [
      '@id' => $event['event_id'], '@producer' => $producerId,
    ]);
    return ['event_id' => $event['event_id'], 'status' => 'rejected', 'code' => 'payload_conflict'];
  }

  private function existingHash(string $siteId, string $producerId, string $eventId): ?string {
    $hash = $this->database->select(self::TABLE, 'e')
      ->fields('e', ['payload_hash'])
      ->condition('site_id', $siteId)
      ->condition('producer_id', $producerId)
      ->condition('event_id', $eventId)
      ->execute()
      ->fetchField();
    return $hash === FALSE ? NULL : (string) $hash;
  }

  private function attemptRevisionExists(string $siteId, string $attemptId, string $eventType,
    int $observationRevision): bool {
    return (bool) $this->database->select(self::TABLE, 'e')
      ->fields('e', ['id'])
      ->condition('site_id', $siteId)
      ->condition('attempt_id', $attemptId)
      ->condition('event_type', $eventType)
      ->condition('observation_revision', $observationRevision)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

}
