<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\EventSubscriber;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests multiple guardrail sets attached to a single input.
 *
 * Exercises the new InputInterface::addGuardrailSet() API and verifies that
 * GuardrailsEventSubscriber iterates every attached set while still
 * short-circuiting once a set's stop threshold is hit.
 *
 * @group ai
 * @covers \Drupal\ai\EventSubscriber\GuardrailsEventSubscriber
 * @covers \Drupal\ai\OperationType\InputBase
 *
 * @see https://www.drupal.org/project/ai/issues/3584849
 */
class MultipleGuardrailSetsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Both sets pass: each stub runs once and provider is not short-circuited.
   */
  public function testBothSetsRunWhenBothPass(): void {
    $this->createSet('set_a', 'stub_a', 'pass');
    $this->createSet('set_b', 'stub_b', 'pass');

    $input = $this->buildInputWithSets(['set_a', 'set_b']);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    // EchoAI echoes the input back when no guardrail forces an output.
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());

    // Each stub fired at least once (pre-generate; post-generate has none
    // configured so the post-hook short-circuits on the empty loop).
    $this->assertSame(1, $this->invocations('stub_a'));
    $this->assertSame(1, $this->invocations('stub_b'));
  }

  /**
   * First set stops: the second set's guardrails must never run.
   */
  public function testFirstSetFailingBlocksSecond(): void {
    $this->createSet('set_a', 'stub_a', 'stop', 'Blocked by A');
    $this->createSet('set_b', 'stub_b', 'pass');

    $input = $this->buildInputWithSets(['set_a', 'set_b']);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked by A', $result->getNormalized()->getText());

    $this->assertSame(1, $this->invocations('stub_a'));
    $this->assertSame(0, $this->invocations('stub_b'), 'Second set must not run after the first set stops.');
  }

  /**
   * First set passes, second set stops: the block still fires from set B.
   */
  public function testFirstPassesSecondFailsStillBlocks(): void {
    $this->createSet('set_a', 'stub_a', 'pass');
    $this->createSet('set_b', 'stub_b', 'stop', 'Blocked by B');

    $input = $this->buildInputWithSets(['set_a', 'set_b']);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked by B', $result->getNormalized()->getText());

    $this->assertSame(1, $this->invocations('stub_a'));
    $this->assertSame(1, $this->invocations('stub_b'));
  }

  /**
   * Two sub-threshold stops in separate sets must not aggregate across sets.
   */
  public function testThresholdsAreIsolatedPerSet(): void {
    // Each stub returns StopResult score 0.4, but each set's threshold is
    // 1.0 — so alone neither set crosses its own threshold.
    $this->createSet('set_a', 'stub_a', 'stop', 'A partial', 0.4, 1.0);
    $this->createSet('set_b', 'stub_b', 'stop', 'B partial', 0.4, 1.0);

    $input = $this->buildInputWithSets(['set_a', 'set_b']);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    // Neither set alone tripped its threshold, so EchoAI's normal echo runs.
    $this->assertStringNotContainsString('A partial', $result->getNormalized()->getText());
    $this->assertStringNotContainsString('B partial', $result->getNormalized()->getText());

    $this->assertSame(1, $this->invocations('stub_a'));
    $this->assertSame(1, $this->invocations('stub_b'));
  }

  /**
   * Create a guardrail entity + set wrapping the counting stub plugin.
   */
  private function createSet(string $set_id, string $tag, string $mode, string $message = 'stub', float $score = 1.0, float $threshold = 1.0): void {
    $guardrail = AiGuardrail::create([
      'id' => $set_id . '_guardrail',
      'label' => $set_id . ' guardrail',
      'description' => 'test stub',
      'guardrail' => 'counting_stub_guardrail',
      'guardrail_settings' => [
        'tag' => $tag,
        'mode' => $mode,
        'message' => $message,
        'score' => $score,
      ],
    ]);
    $guardrail->save();

    $set = AiGuardrailSet::create([
      'id' => $set_id,
      'label' => $set_id,
      'description' => 'test set',
      'stop_threshold' => $threshold,
      'pre_generate_guardrails' => ['plugin_id' => [$set_id . '_guardrail']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ]);
    $set->save();
  }

  /**
   * Build a ChatInput with the listed sets attached in order.
   */
  private function buildInputWithSets(array $set_ids): ChatInput {
    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    foreach ($set_ids as $id) {
      $input = $helper->applyGuardrailSetToChatInput($id, $input);
    }
    $this->assertCount(count($set_ids), $input->getGuardrailSets());
    return $input;
  }

  /**
   * Returns how many times a tagged stub has been invoked.
   */
  private function invocations(string $tag): int {
    return (int) \Drupal::state()->get('ai_test.counting_stub.' . $tag, 0);
  }

}
