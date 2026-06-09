<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Non-deterministic stub that suspends the current Fiber mid-processInput().
 *
 * Simulates the suspension point that occurs when an LLM-based guardrail
 * makes a streaming inner provider call — the provider calls
 * \Fiber::suspend() on each chunk, yielding control to the scheduler.
 *
 * Used by testFiberDepthCountersAreIndependent() to verify that
 * GuardrailsEventSubscriber's per-fiber depth counter prevents a suspended
 * fiber's depth from bleeding into a concurrently running fiber.
 */
#[AiGuardrail(
  id: 'fiber_suspending_nondet_stub_guardrail',
  label: new TranslatableMarkup('Fiber-Suspending Non-Deterministic Stub Guardrail'),
  description: new TranslatableMarkup('Test-only LLM-based guardrail that suspends the current Fiber.'),
)]
class FiberSuspendingNonDeterministicStubGuardrail extends CountingStubGuardrail implements NonDeterministicGuardrailInterface {

  use NeedsAiPluginManagerTrait;

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    $result = parent::processInput($input);

    // Suspend the fiber to simulate what a streaming inner LLM call does.
    // This is the critical yield point: depth[this_fiber] is already > 0
    // at this moment, and a concurrently scheduled fiber must not see it.
    if (\Fiber::getCurrent() !== NULL) {
      \Fiber::suspend();
    }

    return $result;
  }

}
