<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** A concurrent deletion or update invalidated the submitted relationship snapshot. */
final class ConversationReferenceConflict extends ConflictHttpException {

  public function __construct() {
    parent::__construct('Conversation references changed. Reload and retry.');
  }

}
