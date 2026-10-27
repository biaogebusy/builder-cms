<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

/** Provides stable failures without exposing file paths or internal exceptions. */
final class MediaException extends \RuntimeException {

  public function __construct(public readonly string $reason, public readonly int $status = 400) {
    parent::__construct($reason);
  }

}
