<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PrivateKey;

/**
 * 票据格式:base64url("{expiresAt}.{uid}.{signature}")
 * signature = HMAC-SHA256("{jobUuid}|{uid}|{expiresAt}", privateKey)。
 */
final class EventTicketService implements EventTicketServiceInterface {

  private const DEFAULT_TTL = 300;

  public function __construct(
    private readonly PrivateKey $privateKey,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function issue(string $jobUuid, int $uid, ?int $ttl = NULL): string {
    $ttl = $ttl ?? (int) ($this->configFactory->get('xinshi_ai.settings')->get('event_ticket.ttl') ?: self::DEFAULT_TTL);
    $expiresAt = time() + $ttl;
    $signature = $this->sign($jobUuid, $uid, $expiresAt);
    return $this->base64UrlEncode("{$expiresAt}.{$uid}.{$signature}");
  }

  /**
   * {@inheritdoc}
   */
  public function verify(string $ticket, string $jobUuid): bool {
    $decoded = $this->base64UrlDecode($ticket);
    $parts = explode('.', $decoded);
    if (count($parts) !== 3) {
      return FALSE;
    }
    [$expiresAt, $uid, $signature] = $parts;
    $expiresAt = (int) $expiresAt;

    if ($expiresAt < time()) {
      return FALSE;
    }
    $expected = $this->sign($jobUuid, (int) $uid, $expiresAt);
    return hash_equals($expected, $signature);
  }

  private function sign(string $jobUuid, int $uid, int $expiresAt): string {
    return hash_hmac('sha256', "{$jobUuid}|{$uid}|{$expiresAt}", $this->privateKey->get());
  }

  private function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  private function base64UrlDecode(string $data): string {
    return (string) base64_decode(strtr($data, '-_', '+/'), TRUE);
  }

}
