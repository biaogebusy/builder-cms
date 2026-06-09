<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Non-deterministic stub that performs its own inner provider chat().
 *
 * Mirrors RestrictToTopic's shape — an LLM-based guardrail that, inside
 * processInput(), issues a second $ai_provider->chat() call. The recursion
 * guard lives in GuardrailsEventSubscriber (depth counter), so no manual
 * opt-out on the inner ChatInput is needed.
 *
 * To stay terminating even when the recursion guard regresses the stub only
 * issues its inner chat() on the first invocation per request — if it is
 * ever re-entered the invocation count goes up, the test asserts on the
 * count, and the recursion ends. The signal is the count, not a crash.
 */
#[AiGuardrail(
  id: 'inner_chat_nondet_stub_guardrail',
  label: new TranslatableMarkup('Inner-Chat Non-Deterministic Stub Guardrail'),
  description: new TranslatableMarkup('Test-only LLM-based guardrail that performs its own chat() call.'),
)]
class InnerChatNonDeterministicStubGuardrail extends CountingStubGuardrail implements NonDeterministicGuardrailInterface {

  use NeedsAiPluginManagerTrait;

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    // Parent records the invocation in state and produces the pass/stop
    // result per configuration.
    $result = parent::processInput($input);

    // Only fire the inner chat() on the first invocation so the test stays
    // terminating even if the recursion-prevention regresses. The test
    // asserts on the recorded count, which goes to 2 (not infinity) if the
    // inner request is wrongly re-guarded.
    $tag = (string) ($this->configuration['tag'] ?? $this->getPluginId());
    $count = (int) $this->state->get('ai_test.counting_stub.' . $tag, 0);
    if ($count !== 1) {
      return $result;
    }

    // Inner ChatInput — GuardrailsEventSubscriber's depth counter prevents
    // re-entry automatically; no manual opt-out needed on this input.
    $inner = new ChatInput([new ChatMessage('user', 'inner classification')]);

    $provider = $this->getAiPluginManager()->createInstance('echoai');
    // @phpstan-ignore-next-line
    $provider->chat($inner, 'gpt-test', ['ai']);

    return $result;
  }

}
