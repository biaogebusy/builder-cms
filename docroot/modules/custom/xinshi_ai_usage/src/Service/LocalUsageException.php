<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * A local usage fact could not be written; `usageCode` is a stable machine code.
 */
final class LocalUsageException extends \RuntimeException {

  public function __construct(public readonly string $usageCode, string $message, ?\Throwable $previous = NULL) {
    parent::__construct($message, 0, $previous);
  }

}
