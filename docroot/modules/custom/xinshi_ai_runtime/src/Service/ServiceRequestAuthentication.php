<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_runtime\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/** Verifies independent service signatures without loading user or metering credentials. */
final class ServiceRequestAuthentication {

  private const MAX_SKEW = 300;

  public function __construct(
    private readonly ServiceIdentity $identity,
    private readonly KeyValueExpirableFactoryInterface $keyValue,
    private readonly TimeInterface $time,
  ) {}

  /** Authentication consumes the nonce atomically for the entire timestamp window. */
  public function authenticate(Request $request): void {
    $producer = (string) $request->headers->get(ServiceIdentity::HEADER_PRODUCER, '');
    $timestamp = (string) $request->headers->get(ServiceIdentity::HEADER_TIMESTAMP, '');
    $nonce = (string) $request->headers->get(ServiceIdentity::HEADER_NONCE, '');
    $signature = (string) $request->headers->get(ServiceIdentity::HEADER_SIGNATURE, '');
    $secret = $this->identity->secretOf($producer);
    $now = $this->time->getRequestTime();
    if (!preg_match(ServiceIdentity::PRODUCER_PATTERN, $producer) ||
      !preg_match('/^[A-Za-z0-9_-]{16,64}\z/', $nonce) || !preg_match('/^\d{1,12}\z/', $timestamp) ||
      !preg_match('/^[0-9a-f]{64}\z/', $signature) || $secret === NULL ||
      abs($now - (int) $timestamp) > self::MAX_SKEW ||
      !hash_equals(ServiceIdentity::sign($secret, $producer, $timestamp, $nonce, $request->getMethod(),
        $request->getBaseUrl() . $request->getPathInfo(), (string) $request->getContent()), $signature)) {
      throw new UnauthorizedHttpException('Xinshi-HMAC', 'Service authentication failed.');
    }
    if (!$this->keyValue->get('xinshi_ai_service.nonce')->setWithExpireIfNotExists(
      "$producer:$nonce", $now, self::MAX_SKEW * 2)) {
      throw new UnauthorizedHttpException('Xinshi-HMAC', 'Service authentication failed.');
    }
  }

}
