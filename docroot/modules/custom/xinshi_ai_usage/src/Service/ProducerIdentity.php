<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies the HMAC service identity of a registered usage producer.
 *
 * Producers are declared in settings.php, never in exportable configuration:
 * @code
 * $settings['xinshi_ai_usage.producers'] = [
 *   'chat-node' => ['secret' => getenv('METERING_INGEST_SECRET'), 'site_id' => 'example.test'],
 * ];
 * @endcode
 *
 * The producer signs `producer\ntimestamp\nnonce\nMETHOD\npath\nsha256(body)`
 * with HMAC-SHA256. A timestamp outside the allowed skew or a nonce seen before
 * is refused, so a captured request cannot be replayed after the window; the
 * event IDs inside the batch remain the durable dedup key.
 */
final class ProducerIdentity {

  public const HEADER_PRODUCER = 'X-Xinshi-Producer';
  public const HEADER_TIMESTAMP = 'X-Xinshi-Timestamp';
  public const HEADER_NONCE = 'X-Xinshi-Nonce';
  public const HEADER_SIGNATURE = 'X-Xinshi-Signature';
  public const MAX_SKEW_SECONDS = 300;
  private const NONCE_COLLECTION = 'xinshi_ai_usage.nonce';
  private const NONCE_PATTERN = '/^[A-Za-z0-9_-]{16,64}$/';
  private const PRODUCER_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/';

  public function __construct(
    private readonly Settings $settings,
    private readonly KeyValueExpirableFactoryInterface $keyValue,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns ['producer_id' => ..., 'site_id' => ...] for a correctly signed request.
   *
   * @throws \Drupal\xinshi_ai_usage\Service\ServiceAuthException
   */
  public function authenticate(Request $request): array {
    $producer = (string) $request->headers->get(self::HEADER_PRODUCER, '');
    $timestamp = (string) $request->headers->get(self::HEADER_TIMESTAMP, '');
    $nonce = (string) $request->headers->get(self::HEADER_NONCE, '');
    $signature = (string) $request->headers->get(self::HEADER_SIGNATURE, '');
    if (!preg_match(self::PRODUCER_PATTERN, $producer) || !preg_match(self::NONCE_PATTERN, $nonce)
      || !preg_match('/^\d{1,12}$/', $timestamp) || !preg_match('/^[0-9a-f]{64}$/', $signature)) {
      throw new ServiceAuthException('unauthenticated', 'missing or malformed producer signature');
    }
    $registered = $this->producers()[$producer] ?? NULL;
    $secret = $registered['secret'] ?? NULL;
    $siteId = $registered['site_id'] ?? NULL;
    if (!is_string($secret) || $secret === '' || !is_string($siteId) || $siteId === '') {
      throw new ServiceAuthException('unauthenticated', 'unknown producer');
    }
    $now = $this->time->getRequestTime();
    if (abs($now - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
      throw new ServiceAuthException('unauthenticated', 'signature timestamp outside the allowed window');
    }
    $expected = self::sign($secret, $producer, $timestamp, $nonce, $request->getMethod(),
      $request->getBaseUrl() . $request->getPathInfo(), (string) $request->getContent());
    if (!hash_equals($expected, $signature)) {
      throw new ServiceAuthException('unauthenticated', 'invalid signature');
    }
    // Remember the nonce for the whole skew window on both sides of "now".
    $store = $this->keyValue->get(self::NONCE_COLLECTION);
    if (!$store->setWithExpireIfNotExists("$producer:$nonce", $now, self::MAX_SKEW_SECONDS * 2)) {
      throw new ServiceAuthException('unauthenticated', 'signature nonce already used');
    }
    return ['producer_id' => $producer, 'site_id' => $siteId];
  }

  /**
   * The exact string a producer must sign; shared with tests and documentation.
   */
  public static function sign(string $secret, string $producer, string $timestamp, string $nonce,
    string $method, string $path, string $body): string {
    $material = implode("\n", [$producer, $timestamp, $nonce, strtoupper($method), $path, hash('sha256', $body)]);
    return hash_hmac('sha256', $material, $secret);
  }

  private function producers(): array {
    $producers = $this->settings->get('xinshi_ai_usage.producers', []);
    return is_array($producers) ? $producers : [];
  }

}
