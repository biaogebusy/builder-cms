<?php

namespace Drupal\xinshi_api;

/** An expected refusal. Only input and permission refusals confirm no new write. */
final class PageDraftException extends \RuntimeException {

  public function __construct(public readonly string $reason, public readonly int $httpStatus) {
    parent::__construct($reason);
  }

}
