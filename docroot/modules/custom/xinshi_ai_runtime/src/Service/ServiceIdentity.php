<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_runtime\Service;

use Drupal\Core\Site\Settings;

/** CMS-to-Node credentials do not depend on usage collection or producer registration. */
class ServiceIdentity {

  public const HEADER_PRODUCER = 'X-Xinshi-Producer';
  public const HEADER_TIMESTAMP = 'X-Xinshi-Timestamp';
  public const HEADER_NONCE = 'X-Xinshi-Nonce';
  public const HEADER_SIGNATURE = 'X-Xinshi-Signature';
  public const PRODUCER_PATTERN = '/^[A-Za-z0-9_.-]{1,64}\z/';

  public function __construct(private readonly Settings $settings) {}

  /** Secrets belong in settings.php, never in exported configuration. */
  public function secretOf(string $producerId): ?string {
    $secrets = $this->settings->get('xinshi_ai_service.secrets', []);
    $secret = is_array($secrets) ? ($secrets[$producerId] ?? NULL) : NULL;
    return is_string($secret) && $secret !== '' ? $secret : NULL;
  }

  /** Both service transports sign the same bounded identity envelope. */
  public static function sign(string $secret, string $producer, string $timestamp, string $nonce,
    string $method, string $path, string $body): string {
    $material = implode("\n", [$producer, $timestamp, $nonce, strtoupper($method), $path, hash('sha256', $body)]);
    return hash_hmac('sha256', $material, $secret);
  }

}
