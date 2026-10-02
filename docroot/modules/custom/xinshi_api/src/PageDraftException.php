<?php

namespace Drupal\xinshi_api;

/** Expected refusal; controllers distinguish rolled-back writes from uncertain failures. */
final class PageDraftException extends \RuntimeException {

  public function __construct(
    public readonly string $reason,
    public readonly int $httpStatus,
    public readonly ?string $detail = NULL,
  ) {
    parent::__construct($reason);
  }

}
