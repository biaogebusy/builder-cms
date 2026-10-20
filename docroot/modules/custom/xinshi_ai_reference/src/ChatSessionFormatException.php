<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

/** A bounded format code; source content never belongs in exception messages. */
final class ChatSessionFormatException extends \RuntimeException {

  public function __construct(public readonly string $reason) {
    parent::__construct($reason);
  }

}
