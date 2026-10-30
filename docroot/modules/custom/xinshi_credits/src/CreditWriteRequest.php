<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

/** Closed JSON write envelope, in addition to the route's Drupal CSRF check. */
final class CreditWriteRequest {

  public function __construct(private readonly SessionConfigurationInterface $sessions, private readonly array $cors) {}

  /** Require an explicit object and idempotency key; verify session request origins. */
  public function parse(Request $request): array {
    if ($request->getContentTypeFormat() !== 'json') throw new CreditException('invalid_content_type', 415);
    if (strlen($request->getContent()) > 8192) throw new CreditException('request_too_large', 413);
    CreditValue::fields($request->query->all(), []);
    if ($this->sessions->hasSession($request)) {
      $origin = $request->headers->get('Origin');
      $allowed = [$request->getSchemeAndHttpHost()];
      if (($this->cors['supportsCredentials'] ?? FALSE) === TRUE) {
        $allowed = [...$allowed, ...array_diff($this->cors['allowedOrigins'] ?? [], ['*', 'null'])];
      }
      if (!$origin || !in_array($origin, $allowed, TRUE)) throw new CreditException('invalid_origin', 403);
    }
    try { $body = json_decode($request->getContent(), FALSE, 16, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new CreditException('invalid_json'); }
    if (!$body instanceof \stdClass) throw new CreditException('invalid_json');
    return [(array) $body, CreditValue::id($request->headers->get('Idempotency-Key'))];
  }

}
