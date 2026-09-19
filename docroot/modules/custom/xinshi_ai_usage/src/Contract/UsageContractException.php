<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Contract;

/**
 * A payload that does not satisfy the usage event contract.
 *
 * The code is returned to the producer in the receipt so it can quarantine the
 * event instead of retrying it unchanged.
 */
final class UsageContractException extends \InvalidArgumentException {

  public function __construct(
    public readonly string $contractCode,
    string $message,
  ) {
    parent::__construct($message);
  }

}
