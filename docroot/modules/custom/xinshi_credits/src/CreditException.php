<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

/** A stable public failure without database or private evidence details. */
final class CreditException extends \RuntimeException {

  public function __construct(public readonly string $reason, public readonly int $status = 422) {
    parent::__construct($reason);
  }

}
