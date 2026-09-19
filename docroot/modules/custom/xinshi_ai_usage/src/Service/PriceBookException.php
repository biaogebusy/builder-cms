<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * Exception thrown when a price book lookup or rate application fails.
 *
 * The code is safe to return in an admin-facing error; it never includes
 * secrets, full payloads or prompt content.
 */
final class PriceBookException extends \RuntimeException {

  public function __construct(
    public readonly string $priceCode,
    string $message,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, 0, $previous);
  }

}
