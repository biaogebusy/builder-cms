<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/** Provider diagnostics and credentials never become API responses. */
final class FigmaException extends \RuntimeException {

  public function __construct(public readonly string $error, public readonly int $status = 400) {
    parent::__construct($error);
  }

}
