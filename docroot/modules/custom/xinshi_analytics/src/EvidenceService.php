<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\Site\Settings;

/** Seals an immutable count and rechecks its current source before releasing it. */
final class EvidenceService {

  /** Shares the exact access-checked scan with ordinary entity counts. */
  public function __construct(private readonly CountService $counts) {}

  /** Issues an opaque receipt without storing results or entity identifiers in Drupal. */
  public function capture(array $query): array {
    $snapshot = $this->counts->snapshot($query);
    $evidence = ['version' => 1, 'result' => $snapshot['result'],
      'receipt' => $this->seal($snapshot['result'], $snapshot)];
    if (strlen(json_encode($evidence, JSON_THROW_ON_ERROR)) > 1024 * 1024) {
      throw new AnalyticsException('query_failed');
    }
    return $evidence;
  }

  /** Never replace historical counts with the newly computed verification result. */
  public function verify(array $evidence): array {
    if (!CountQuery::keys($evidence, ['version', 'result', 'receipt']) || $evidence['version'] !== 1
      || !is_string($evidence['receipt']) || !preg_match('/^[a-f0-9]{64}$/D', $evidence['receipt'])
      || !CountQuery::keys($evidence['result'], ['query', 'rows', 'completeness', 'startedAt', 'finishedAt'])
      || !is_array($evidence['result']['query'])) {
      throw new AnalyticsException('invalid_query');
    }
    CountQuery::validate($evidence['result']['query']);
    try {
      $snapshot = $this->counts->snapshot($evidence['result']['query']);
    }
    catch (AnalyticsException $error) {
      // An outage differs from a stale receipt, but neither authorizes old data.
      throw new AnalyticsException($error->getMessage() === 'query_failed'
        ? 'query_failed' : 'evidence_unavailable');
    }
    if (self::canonical($snapshot['result']['rows']) !== self::canonical($evidence['result']['rows'])
      || !hash_equals($this->seal($evidence['result'], $snapshot), $evidence['receipt'])) {
      throw new AnalyticsException('evidence_unavailable');
    }
    return ['valid' => TRUE];
  }

  /** Domain separation and the site secret bind the original result to the current source. */
  private function seal(array $result, array $snapshot): string {
    return hash_hmac('sha256', "xinshi-analytics-evidence-v1\0" . json_encode(self::canonical([
      'result' => $result, 'uid' => $snapshot['uid'], 'epochs' => $snapshot['epochs'],
      'definition' => $snapshot['definition'], 'fingerprint' => $snapshot['fingerprint'],
    ]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), Settings::getHashSalt());
  }

  /** JSON object key ordering is not evidence identity; ordered arrays remain ordered. */
  private static function canonical(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value, SORT_STRING);
    }
    return array_map(self::canonical(...), $value);
  }

}
