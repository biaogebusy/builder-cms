<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Returns stable failure codes without document contents or internal paths. */
final class OfficeException extends \RuntimeException {

  public function __construct(public readonly string $reason, public readonly int $status = 422) {
    parent::__construct($reason);
  }

}

