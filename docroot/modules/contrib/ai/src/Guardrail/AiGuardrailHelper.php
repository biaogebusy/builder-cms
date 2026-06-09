<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\OperationType\InputInterface;

/**
 * Helper class for guardrail operations.
 */
class AiGuardrailHelper {

  /**
   * Constructs a new AiGuardrailHelper object.
   *
   * @param \Drupal\ai\Guardrail\AiGuardrailRepository $ai_guardrail_repository
   *   The AI guardrail repository.
   */
  public function __construct(
    private readonly AiGuardrailRepository $ai_guardrail_repository,
  ) {
  }

  /**
   * Applies a guardrail set to an input.
   *
   * @param string $guardrail_set_id
   *   The guardrail set ID.
   * @param T $input
   *   The input to apply the guardrail set to.
   *
   * @template T of \Drupal\ai\OperationType\InputInterface
   *
   * @return T
   *   The modified input with the guardrail set applied, or the original
   *   input if no guardrail set was found.
   */
  public function applyGuardrailSetToChatInput(string $guardrail_set_id, InputInterface $input): InputInterface {
    if ($guardrail_set_id !== '') {
      /** @var \Drupal\ai\Entity\AiGuardrailSet|null $guardrail_set */
      $guardrail_set = $this->ai_guardrail_repository->getGuardrailSetById($guardrail_set_id);

      if ($guardrail_set !== NULL) {
        $input_with_guardrails = clone $input;
        $input_with_guardrails->addGuardrailSet($guardrail_set);

        return $input_with_guardrails;
      }
    }

    return $input;
  }

  /**
   * Gets the AI guardrail repository.
   *
   * @return \Drupal\ai\Guardrail\AiGuardrailRepository
   *   The AI guardrail repository.
   */
  public function getRepository(): AiGuardrailRepository {
    return $this->ai_guardrail_repository;
  }

}
