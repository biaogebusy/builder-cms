<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

use Drupal\ai\Guardrail\AiGuardrailInterface;

/**
 * A guardrail result that indicates the input should not be processed further.
 */
class StopResult extends AbstractResult {

  public function __construct(
    string $message,
    AiGuardrailInterface $guardrail,
    array $context = [],
    protected readonly float $score = 1.0,
  ) {
    parent::__construct($message, $guardrail, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function stop(): bool {
    return TRUE;
  }

  /**
   * Gets the score associated with the stop result.
   */
  public function getScore() : float {
    return $this->score;
  }

}
