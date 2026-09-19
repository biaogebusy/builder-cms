<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * A request that carries no valid producer signature.
 */
final class ServiceAuthException extends \RuntimeException {

  public function __construct(
    public readonly string $authCode,
    string $message,
  ) {
    parent::__construct($message);
  }

}
