<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

/**
 * A guardrail result that indicates the input should be rewritten.
 */
class RewriteInputResult extends AbstractResult {

  /**
   * {@inheritdoc}
   */
  public function stop(): bool {
    return FALSE;
  }

}
