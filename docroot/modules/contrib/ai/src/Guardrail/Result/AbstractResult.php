<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail\Result;

use Drupal\ai\Guardrail\AiGuardrailInterface;

/**
 * Abstract class for guardrail results.
 */
abstract class AbstractResult implements GuardrailResultInterface {

  public function __construct(
    protected readonly string $message,
    protected readonly AiGuardrailInterface $guardrail,
    protected readonly array $context = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * {@inheritdoc}
   */
  public function getGuardrailLabel(): string {
    return $this->guardrail->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return $this->context;
  }

}
