<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\RewriteInputResult;
use Drupal\ai\Guardrail\Result\RewriteOutputResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test-only guardrail that records every invocation in state.
 *
 * Use plugin configuration to control behavior:
 *   - tag (string): unique key used for the invocation counter.
 *   - mode (string): one of 'pass', 'stop', 'rewrite_input', 'rewrite_output'.
 *   - score (float): score returned on stop (default 1.0).
 *   - message (string): message returned (rewrite uses it as the new text).
 *
 * Invocation counts are written to
 *   \Drupal::state()->get('ai_test.counting_stub.' . $tag).
 *
 * When mode is 'rewrite_input', the plugin also records the text of the
 * last ChatMessage it saw under
 *   \Drupal::state()->get('ai_test.counting_stub.' . $tag . '.seen')
 * so tests can assert whether the guardrail observed the un-rewritten
 * prompt or a version that a prior guardrail already mutated.
 */
#[AiGuardrail(
  id: 'counting_stub_guardrail',
  label: new TranslatableMarkup('Counting Stub Guardrail'),
  description: new TranslatableMarkup('Test-only guardrail that records invocations.'),
)]
class CountingStubGuardrail extends AiGuardrailPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a CountingStubGuardrail plugin.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    $this->recordInvocation();

    $mode = $this->configuration['mode'] ?? 'pass';
    if ($mode === 'rewrite_input') {
      $this->recordSeenInputText($input);
      return new RewriteInputResult(
        (string) ($this->configuration['message'] ?? ''),
        $this,
      );
    }
    // On the input phase, rewrite_output behaves like pass — the rewrite
    // only applies when processOutput() runs.
    if ($mode === 'rewrite_output') {
      return new PassResult('Rewrite-output stub passes on input.', $this);
    }

    return $this->passOrStopResult();
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    $this->recordInvocation();

    $mode = $this->configuration['mode'] ?? 'pass';
    if ($mode === 'rewrite_output') {
      return new RewriteOutputResult(
        (string) ($this->configuration['message'] ?? ''),
        $this,
      );
    }
    if ($mode === 'rewrite_input') {
      return new PassResult('Rewrite-input stub passes on output.', $this);
    }

    return $this->passOrStopResult();
  }

  /**
   * Increments the invocation counter for this stub's tag.
   */
  private function recordInvocation(): void {
    $tag = (string) ($this->configuration['tag'] ?? $this->getPluginId());
    $key = 'ai_test.counting_stub.' . $tag;
    $this->state->set($key, ((int) $this->state->get($key, 0)) + 1);
  }

  /**
   * Builds a pass or stop result based on configuration.
   */
  private function passOrStopResult(): GuardrailResultInterface {
    $mode = $this->configuration['mode'] ?? 'pass';
    if ($mode === 'stop') {
      $message = (string) ($this->configuration['message'] ?? 'stopped');
      $score = (float) ($this->configuration['score'] ?? 1.0);
      return new StopResult($message, $this, [], $score);
    }
    return new PassResult((string) ($this->configuration['message'] ?? 'passed'), $this);
  }

  /**
   * Record the last ChatMessage text the stub observed on its input.
   *
   * Lets tests assert whether a rewrite-input guardrail saw the original
   * prompt or one that a prior guardrail had already mutated.
   */
  private function recordSeenInputText(InputInterface $input): void {
    if (!$input instanceof ChatInput) {
      return;
    }
    $messages = $input->getMessages();
    $last = end($messages);
    if (!$last instanceof ChatMessage) {
      return;
    }
    $tag = (string) ($this->configuration['tag'] ?? $this->getPluginId());
    $this->state->set('ai_test.counting_stub.' . $tag . '.seen', $last->getText());
  }

}
