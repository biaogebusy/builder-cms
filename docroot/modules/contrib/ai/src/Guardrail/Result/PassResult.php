<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

/**
 * A guardrail result that indicates the input can pass without changes.
 */
class PassResult extends AbstractResult {

  /**
   * {@inheritdoc}
   */
  public function stop(): bool {
    return FALSE;
  }

}
