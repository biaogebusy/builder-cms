<?php

namespace Drupal\ai\EventSubscriber;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Guardrail\AiGuardrailPluginManager;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\RewriteInputResult;
use Drupal\ai\Guardrail\Result\RewriteOutputResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to AI events and applies guardrails.
 *
 * This subscriber listens to pre- and post-generate response events
 * and applies the configured guardrails to the input and output respectively.
 *
 * @package Drupal\ai\EventSubscriber
 */
class GuardrailsEventSubscriber implements EventSubscriberInterface {

  /**
   * Per-fiber nesting depth of NonDeterministicGuardrailInterface executions.
   *
   * Keyed by fiber identity (spl_object_id of the current Fiber, or 'main'
   * for non-fiber context). Incremented before calling
   * processInput()/processOutput() on any NonDeterministicGuardrailInterface
   * guardrail and decremented in a finally block. When a fiber suspends
   * mid-call (e.g. during streamed inner LLM responses), other fibers that
   * resume must not inherit this fiber's depth — each fiber tracks its own
   * counter independently. Deterministic guardrails are never affected.
   *
   * @var array<int|string, int>
   */
  private array $llmGuardrailDepth = [];

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly AiGuardrailPluginManager $aiGuardrailPluginManager,
    #[Autowire(service: 'ai.provider')]
    protected readonly AiProviderPluginManager $aiProviderPluginManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the depth-counter key for the currently executing fiber.
   *
   * Non-fiber code all shares the 'main' bucket. Each Fiber instance gets
   * its own bucket so that a suspended fiber's depth does not bleed into
   * a concurrently running fiber.
   *
   * @return int|string
   *   spl_object_id of the current Fiber, or 'main'.
   */
  private function fiberKey(): int|string {
    $fiber = \Fiber::getCurrent();
    return $fiber !== NULL ? \spl_object_id($fiber) : 'main';
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'applyPreGenerateGuardrails',
      PostGenerateResponseEvent::EVENT_NAME => 'applyPostGenerateGuardrails',
    ];
  }

  /**
   * Apply pre-generate guardrails.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The pre generate response event.
   */
  public function applyPreGenerateGuardrails(
    PreGenerateResponseEvent $event,
  ): void {
    $input = $event->getInput();

    if (!$input instanceof InputInterface) {
      return;
    }

    $guardrail_sets = $input->getGuardrailSets();
    if ($guardrail_sets === []) {
      return;
    }

    foreach ($guardrail_sets as $guardrail_set) {
      // Score is aggregated per-set so each set's stop threshold is
      // evaluated independently.
      $aggregated_score = 0;
      $aggregated_messages = [];

      foreach ($guardrail_set->getPreGenerateGuardrails() as $guardrail) {
        if ($guardrail instanceof NonDeterministicGuardrailInterface) {
          // Skip if already inside an LLM-based guardrail's execution in
          // this fiber. Each fiber tracks its own depth so that a suspended
          // fiber's counter does not bleed into concurrently running fibers.
          $key = $this->fiberKey();
          if (($this->llmGuardrailDepth[$key] ?? 0) > 0) {
            continue;
          }
          $guardrail->setAiPluginManager($this->aiProviderPluginManager);
          $this->llmGuardrailDepth[$key] = ($this->llmGuardrailDepth[$key] ?? 0) + 1;
          try {
            $result = $guardrail->processInput($input);
          }
          finally {
            $this->llmGuardrailDepth[$key]--;
            if ($this->llmGuardrailDepth[$key] === 0) {
              unset($this->llmGuardrailDepth[$key]);
            }
          }
        }
        else {
          $result = $guardrail->processInput($input);
        }
        $input->addGuardrailResult($result, AiGuardrailModeEnum::PreGenerate);

        if ($result instanceof PassResult) {
          continue;
        }

        if ($result instanceof StopResult) {
          $aggregated_score += $result->getScore();
          $aggregated_messages[] = $result->getMessage();

          if ($aggregated_score >= $guardrail_set->getStopThreshold()) {
            $event->setForcedOutputObject(
              new ChatOutput(
                new ChatMessage('assistant', \implode(' ', $aggregated_messages)),
                $result->getMessage(),
                []
              )
            );

            return;
          }
        }

        if (
          $result instanceof RewriteInputResult &&
          \method_exists($input, 'getMessages') &&
          \method_exists($input, 'setMessages')
        ) {
          $messages = $input->getMessages();
          // Replace the last message with the rewritten one.
          if (!empty($messages)) {
            $last_message = end($messages);
            if ($last_message instanceof ChatMessage) {
              $last_message->setText($result->getMessage());
              $messages[key($messages)] = $last_message;
              $input->setMessages($messages);
              $event->setInput($input);
            }
          }
        }
      }
    }
  }

  /**
   * Apply post-request guardrails.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The post generate response event.
   */
  public function applyPostGenerateGuardrails(
    PostGenerateResponseEvent $event,
  ): void {
    $output = $event->getOutput();

    $input = $event->getInput();
    if (!$input instanceof InputInterface) {
      return;
    }

    $guardrail_sets = $input->getGuardrailSets();
    if ($guardrail_sets === []) {
      return;
    }

    foreach ($guardrail_sets as $guardrail_set) {
      $aggregated_score = 0;
      $aggregated_messages = [];

      $streaming_iterator = ($output->getNormalized() instanceof StreamedChatMessageIteratorInterface)
        ? $output->getNormalized()
        : NULL;

      foreach ($guardrail_set->getPostGenerateGuardrails() as $guardrail) {
        if ($guardrail instanceof NonDeterministicGuardrailInterface) {
          // Skip if already inside an LLM-based guardrail's execution in
          // this fiber. See applyPreGenerateGuardrails() for full explanation.
          $key = $this->fiberKey();
          if (($this->llmGuardrailDepth[$key] ?? 0) > 0) {
            continue;
          }
          $guardrail->setAiPluginManager($this->aiProviderPluginManager);
        }

        // Register streaming guardrails directly with the iterator. They
        // operate in real-time during stream iteration, so they must not be
        // run as regular post-generate guardrails.
        if ($streaming_iterator !== NULL && $guardrail instanceof StreamableGuardrailInterface) {
          $streaming_iterator->addStreamingGuardrail($guardrail);
          continue;
        }

        if (
          $output->getNormalized() instanceof StreamedChatMessageIteratorInterface &&
          $guardrail instanceof NonStreamableGuardrailInterface
        ) {
          // Reconstruct the chat output for non-streamable guardrails.
          $output = $output->getNormalized()->reconstructChatOutput();
        }

        if ($guardrail instanceof NonDeterministicGuardrailInterface) {
          $key = $this->fiberKey();
          $this->llmGuardrailDepth[$key] = ($this->llmGuardrailDepth[$key] ?? 0) + 1;
          try {
            $result = $guardrail->processOutput($output);
          }
          finally {
            $this->llmGuardrailDepth[$key]--;
            if ($this->llmGuardrailDepth[$key] === 0) {
              unset($this->llmGuardrailDepth[$key]);
            }
          }
        }
        else {
          $result = $guardrail->processOutput($output);
        }
        $input->addGuardrailResult($result, AiGuardrailModeEnum::PostGenerate);

        if ($result instanceof PassResult) {
          continue;
        }

        if ($result instanceof StopResult) {
          $aggregated_score += $result->getScore();
          $aggregated_messages[] = $result->getMessage();

          if ($aggregated_score >= $guardrail_set->getStopThreshold()) {
            $event->setOutput(
              new ChatOutput(
                new ChatMessage('assistant', \implode(' ', $aggregated_messages)),
                $result->getMessage(),
                []
              )
            );

            return;
          }
        }

        if ($result instanceof RewriteOutputResult) {
          $message = $output->getNormalized();
          // Replace the message with the rewritten one.
          if ($message instanceof ChatMessage) {
            $message->setText($result->getMessage());
            $event->setOutput($output);
          }
        }
      }
    }
  }

}
