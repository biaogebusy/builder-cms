<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools;

/** A bounded public error; internal exceptions never become tool results. */
final class EntityToolException extends \RuntimeException {

  public function __construct(
    public readonly string $reason,
    public readonly int $status = 422,
    public readonly bool $notWritten = FALSE,
  ) {
    parent::__construct($reason);
  }

}
