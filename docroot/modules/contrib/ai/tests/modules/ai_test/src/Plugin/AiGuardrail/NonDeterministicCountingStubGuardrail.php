<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Non-deterministic variant of the counting stub guardrail.
 *
 * Identical behavior to ::CountingStubGuardrail, but marked as LLM-based via
 * NonDeterministicGuardrailInterface. Lets tests verify that the depth-counter
 * guard in GuardrailsEventSubscriber skips this category of guardrail during
 * inner LLM calls while leaving deterministic ones running.
 */
#[AiGuardrail(
  id: 'nondet_counting_stub_guardrail',
  label: new TranslatableMarkup('Non-Deterministic Counting Stub Guardrail'),
  description: new TranslatableMarkup('Test-only LLM-based guardrail that records invocations.'),
)]
class NonDeterministicCountingStubGuardrail extends CountingStubGuardrail implements NonDeterministicGuardrailInterface {

  use NeedsAiPluginManagerTrait;

}
