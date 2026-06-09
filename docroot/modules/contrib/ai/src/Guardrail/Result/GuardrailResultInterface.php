<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

/**
 * Interface for guardrail results.
 */
interface GuardrailResultInterface {

  /**
   * Returns the message associated with the guardrail result.
   */
  public function getMessage(): string;

  /**
   * Returns the label of the guardrail that produced this result.
   */
  public function getGuardrailLabel(): string;

  /**
   * Returns the context associated with the guardrail result.
   */
  public function getContext(): array;

  /**
   * Weather the guardrail result is a stop condition.
   */
  public function stop(): bool;

}
