<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

/**
 * A guardrail result that indicates the output should be rewritten.
 */
class RewriteOutputResult extends AbstractResult {

  /**
   * {@inheritdoc}
   */
  public function stop(): bool {
    return FALSE;
  }

}
