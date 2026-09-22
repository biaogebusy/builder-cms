<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * A usage report request that cannot be served, with a machine code for the API envelope.
 */
final class UsageReportException extends \RuntimeException {

  public function __construct(
    public readonly string $reportCode,
    string $message,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, 0, $previous);
  }

}
