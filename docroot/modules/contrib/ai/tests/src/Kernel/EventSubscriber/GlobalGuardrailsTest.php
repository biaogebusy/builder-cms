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
 * Tests the global guardrails setting.
 *
 * @group ai
 * @covers \Drupal\ai\EventSubscriber\GlobalGuardrailsEventSubscriber
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
class GlobalGuardrailsTest extends KernelTestBase {

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
    $this->installConfig(['ai']);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Global set applies when the input has no set attached.
   */
  public function testGlobalSetAppliesWithoutInputSet(): void {
    $this->createSet('global_a', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['global_a']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    // No stop, normal echo passes through.
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
  }

  /**
   * Global set runs in addition to an input-attached set.
   */
  public function testGlobalSetCombinesWithInputSet(): void {
    $this->createSet('input_set', 'stub_input', 'pass');
    $this->createSet('global_set', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['global_set']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('input_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_input'), 'Input-attached set ran.');
    $this->assertSame(1, $this->invocations('stub_global'), 'Global set ran.');
  }

  /**
   * A global set crossing its threshold blocks the request.
   */
  public function testGlobalSetStopThresholdBlocks(): void {
    $this->createSet('global_block', 'stub_global', 'stop', 'Blocked globally');
    $this->setGlobalGuardrails(['global_block']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked globally', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
  }

  /**
   * Missing / unknown global set ids are silently ignored.
   */
  public function testUnknownGlobalSetIdsAreIgnored(): void {
    $this->setGlobalGuardrails(['does_not_exist']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
  }

  /**
   * A global rewrite-input guardrail mutates the prompt before the provider.
   */
  public function testGlobalSetRewritesInputBeforeProvider(): void {
    $this->createSet('global_rewrite', 'stub_global', 'rewrite_input', 'Sanitized prompt');
    $this->setGlobalGuardrails(['global_rewrite']);

    $input = new ChatInput([new ChatMessage('user', 'Original prompt')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Sanitized prompt', $result->getNormalized()->getText());
    $this->assertStringNotContainsString('Original prompt', $result->getNormalized()->getText());
  }

  /**
   * A global rewrite-output guardrail mutates the provider response.
   */
  public function testGlobalSetRewritesOutput(): void {
    $this->createSetPostGenerate('global_out_rewrite', 'stub_global', 'rewrite_output', 'Redacted');
    $this->setGlobalGuardrails(['global_out_rewrite']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertSame('Redacted', $result->getNormalized()->getText());
  }

  /**
   * Regression guard for #3584851: globals run before caller-attached sets.
   *
   * A caller-attached RewriteInputResult must not reach the provider without
   * the global set having evaluated the original, un-mutated prompt first.
   */
  public function testGlobalRewriteRunsBeforeCallerRewrite(): void {
    // Global: observes the prompt and rewrites it to a sentinel value.
    $this->createSet('global_first', 'stub_global', 'rewrite_input', 'GLOBAL_REWROTE');
    // Caller-attached: would also rewrite if it got to run first.
    $this->createSet('caller_set', 'stub_caller', 'rewrite_input', 'Caller rewrote');
    $this->setGlobalGuardrails(['global_first']);

    $input = new ChatInput([new ChatMessage('user', 'Original prompt')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('caller_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);

    // The global ran first and saw the ORIGINAL prompt — this is the
    // load-bearing assertion. Before the ordering fix this was 'Original
    // prompt' only if the global happened to be first, which it was not.
    $this->assertSame(
      'Original prompt',
      \Drupal::state()->get('ai_test.counting_stub.stub_global.seen'),
      'Global guardrail must evaluate the original prompt before any caller rewrite.',
    );

    // Caller ran second and saw what the global wrote.
    $this->assertSame(
      'GLOBAL_REWROTE',
      \Drupal::state()->get('ai_test.counting_stub.stub_caller.seen'),
    );

    // Last writer wins on the final ChatMessage, so the caller's rewrite
    // is what the provider echoed back.
    $this->assertStringContainsString('Caller rewrote', $result->getNormalized()->getText());
  }

  /**
   * A global stop short-circuits caller-attached sets entirely.
   */
  public function testGlobalStopPreventsCallerSetsFromRunning(): void {
    $this->createSet('global_block', 'stub_global', 'stop', 'Blocked globally');
    $this->createSet('caller_set', 'stub_caller', 'pass');
    $this->setGlobalGuardrails(['global_block']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('caller_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked globally', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
    $this->assertSame(0, $this->invocations('stub_caller'), 'Caller set must not run after a global stop.');
  }

  /**
   * Deleting a globally-configured set leaves subsequent requests working.
   *
   * Covers the resilience path in GlobalGuardrailsEventSubscriber where
   * getGuardrailSetById() returns NULL — the subscriber must skip the missing
   * set and let the request proceed normally.
   */
  public function testDeletedGlobalSetLeavesRequestsWorking(): void {
    $this->createSet('doomed_global', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['doomed_global']);

    AiGuardrailSet::load('doomed_global')->delete();

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(0, $this->invocations('stub_global'), 'Deleted global set must not execute its guardrail.');
  }

  /**
   * Deleting a set auto-cleans its id from ai.settings:global_guardrails.
   *
   * Covers ai_ai_guardrail_set_delete() — on entity delete, the stale id is
   * removed from config so admins who never revisit the form do not carry a
   * dead reference.
   */
  public function testDeletingSetRemovesItFromGlobals(): void {
    $this->createSet('auto_clean_global', 'stub_global', 'pass');
    $this->createSet('kept_global', 'stub_kept', 'pass');
    $this->setGlobalGuardrails(['auto_clean_global', 'kept_global']);

    AiGuardrailSet::load('auto_clean_global')->delete();

    $this->assertSame(
      ['kept_global'],
      $this->config('ai.settings')->get('global_guardrails'),
      'Deleted set id must be removed from global_guardrails; other ids are preserved.',
    );
  }

  /**
   * Empty global guardrails config leaves behavior unchanged.
   */
  public function testEmptyGlobalConfigIsNoop(): void {
    $this->createSet('input_only', 'stub_input', 'pass');
    $this->setGlobalGuardrails([]);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('input_only', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_input'));
  }

  /**
   * During an inner LLM call, only non-deterministic guardrails are skipped.
   *
   * GuardrailsEventSubscriber tracks nesting depth. When depth > 0 (i.e. we
   * are already inside a NonDeterministicGuardrailInterface execution),
   * further NonDeterministicGuardrailInterface guardrails are skipped.
   * Deterministic guardrails in the same set continue to run.
   *
   * Verified by putting both an inner-chat NonDet stub and a Deterministic
   * stub in the same global set. The NonDet stub fires its own inner chat()
   * while depth = 1, so on that inner call: NonDet is skipped (count stays
   * at 1) while Det runs again (count reaches 2).
   */
  public function testInnerCallSkipsNonDeterministicButRunsDeterministicGuardrails(): void {
    // Non-deterministic stub that fires an inner chat() call on first invoke.
    AiGuardrail::create([
      'id' => 'mixed_nondet',
      'label' => 'mixed nondet',
      'description' => 'inner-chat non-deterministic stub',
      'guardrail' => 'inner_chat_nondet_stub_guardrail',
      'guardrail_settings' => [
        'tag' => 'stub_nondet',
        'mode' => 'pass',
        'message' => 'stub',
        'score' => 1.0,
      ],
    ])->save();
    // Deterministic stub: must run on both the outer call and the inner call.
    AiGuardrail::create([
      'id' => 'mixed_det',
      'label' => 'mixed det',
      'description' => 'deterministic stub',
      'guardrail' => 'counting_stub_guardrail',
      'guardrail_settings' => [
        'tag' => 'stub_det',
        'mode' => 'pass',
        'message' => 'stub',
        'score' => 1.0,
      ],
    ])->save();
    AiGuardrailSet::create([
      'id' => 'mixed_set',
      'label' => 'mixed_set',
      'description' => 'mixed set',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => ['mixed_nondet', 'mixed_det']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ])->save();
    $this->setGlobalGuardrails(['mixed_set']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    // NonDet stub ran once (outer call). The inner call had depth > 0, so it
    // was skipped — count stays at 1, not 2.
    $this->assertSame(1, $this->invocations('stub_nondet'), 'Non-deterministic guardrail must be skipped on inner calls (depth > 0).');
    // Deterministic stub ran twice: outer call + inner call.
    $this->assertSame(2, $this->invocations('stub_det'), 'Deterministic guardrail must still run on inner calls.');
  }

  /**
   * Regression: a global LLM-based guardrail's inner chat() is not re-guarded.
   *
   * Exercises the full recursion path the fix covers: a globally-configured
   * NonDeterministicGuardrailInterface guardrail performs its own provider
   * chat() inside processInput(). GuardrailsEventSubscriber's depth counter
   * is already > 0 at that point, so the same guardrail is skipped on the
   * inner request. The assertion pins the invocation count at exactly 1; a
   * regression (depth counter removed, or NonDet check dropped) would let
   * the inner request re-trigger the guardrail and push the count to 2.
   */
  public function testGlobalLlmGuardrailInnerChatIsNotReGuarded(): void {
    AiGuardrail::create([
      'id' => 'recursion_check_guardrail',
      'label' => 'recursion check',
      'description' => 'inner-chat non-det stub',
      'guardrail' => 'inner_chat_nondet_stub_guardrail',
      'guardrail_settings' => [
        'tag' => 'stub_inner_chat',
        'mode' => 'pass',
        'message' => 'stub',
        'score' => 1.0,
      ],
    ])->save();
    AiGuardrailSet::create([
      'id' => 'recursion_check_set',
      'label' => 'recursion_check_set',
      'description' => 'set for inner-chat recursion regression check',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => ['recursion_check_guardrail']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ])->save();
    $this->setGlobalGuardrails(['recursion_check_set']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertSame(
      1,
      $this->invocations('stub_inner_chat'),
      'Inner non-deterministic chat() must not be re-guarded; the stub must be invoked exactly once per outer chat().',
    );
  }

  /**
   * Fiber depth counters are isolated per fiber.
   *
   * Verifies that GuardrailsEventSubscriber tracks depth per-fiber so that a
   * suspended fiber's counter does not bleed into a concurrently running fiber.
   *
   * Scenario: two fibers each make a chat() call guarded by the same global
   * NonDeterministicGuardrailInterface set. Fiber A's stub suspends the fiber
   * mid-processInput() (depth[A] = 1 at that moment). Fiber B then starts and
   * must see depth[B] = 0 — not Fiber A's depth — so its guardrail runs too.
   * If the depth counter were a plain shared int, Fiber B would skip its
   * guardrail and the invocation count would be 1 instead of 2.
   */
  public function testFiberDepthCountersAreIndependent(): void {
    AiGuardrail::create([
      'id' => 'fiber_guard',
      'label' => 'fiber guard',
      'description' => 'fiber-suspending non-det stub',
      'guardrail' => 'fiber_suspending_nondet_stub_guardrail',
      'guardrail_settings' => [
        'tag' => 'stub_fiber',
        'mode' => 'pass',
        'message' => 'stub',
        'score' => 1.0,
      ],
    ])->save();
    AiGuardrailSet::create([
      'id' => 'fiber_set',
      'label' => 'fiber_set',
      'description' => 'set for fiber isolation test',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => ['fiber_guard']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ])->save();
    $this->setGlobalGuardrails(['fiber_set']);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');

    // Fiber A: runs until the stub suspends (depth[A] = 1 at suspension point).
    $fiber_a = new \Fiber(function () use ($provider): void {
      $provider->chat(
        new ChatInput([new ChatMessage('user', 'Hi from A')]),
        'gpt-test',
        ['test'],
      );
    });

    // Fiber B: must see depth[B] = 0 when it starts, not Fiber A's depth.
    $fiber_b = new \Fiber(function () use ($provider): void {
      $provider->chat(
        new ChatInput([new ChatMessage('user', 'Hi from B')]),
        'gpt-test',
        ['test'],
      );
    });

    // Start Fiber A — runs until FiberSuspendingNonDeterministicStubGuardrail
    // calls \Fiber::suspend(). At this point depth[fiber_a_id] = 1.
    $fiber_a->start();

    // Start Fiber B — must see depth[fiber_b_id] = 0 and run its guardrail.
    // Runs until its own stub suspension point.
    $fiber_b->start();

    // Resume both fibers to completion.
    if (!$fiber_a->isTerminated()) {
      $fiber_a->resume();
    }
    if (!$fiber_b->isTerminated()) {
      $fiber_b->resume();
    }

    // Both fibers must have run the guardrail exactly once each.
    // With a shared int counter (pre-fix), Fiber B sees depth = 1 and skips —
    // count stays at 1. With per-fiber depth (post-fix), count = 2.
    $this->assertSame(
      2,
      $this->invocations('stub_fiber'),
      'Each fiber must run the guardrail independently. A count of 1 means the second fiber incorrectly inherited the first fiber\'s depth.',
    );
  }

  /**
   * Create a guardrail entity + set wrapping the counting stub plugin.
   *
   * The stub is wired into the pre-generate slot. Use
   * ::createSetPostGenerate() for post-generate tests.
   */
  private function createSet(string $set_id, string $tag, string $mode, string $message = 'stub', float $score = 1.0, float $threshold = 1.0): void {
    $this->createSetInternal($set_id, $tag, $mode, $message, $score, $threshold, 'pre');
  }

  /**
   * Create a set with the stub wired into the post-generate slot.
   */
  private function createSetPostGenerate(string $set_id, string $tag, string $mode, string $message = 'stub', float $score = 1.0, float $threshold = 1.0): void {
    $this->createSetInternal($set_id, $tag, $mode, $message, $score, $threshold, 'post');
  }

  /**
   * Shared implementation for the two createSet helpers.
   */
  private function createSetInternal(string $set_id, string $tag, string $mode, string $message, float $score, float $threshold, string $phase): void {
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

    $pre = $phase === 'pre' ? [$set_id . '_guardrail'] : [];
    $post = $phase === 'post' ? [$set_id . '_guardrail'] : [];
    $set = AiGuardrailSet::create([
      'id' => $set_id,
      'label' => $set_id,
      'description' => 'test set',
      'stop_threshold' => $threshold,
      'pre_generate_guardrails' => ['plugin_id' => $pre],
      'post_generate_guardrails' => ['plugin_id' => $post],
    ]);
    $set->save();
  }

  /**
   * Sets the global guardrails configuration.
   */
  private function setGlobalGuardrails(array $ids): void {
    $this->config('ai.settings')->set('global_guardrails', $ids)->save();
  }

  /**
   * Returns how many times a tagged stub has been invoked.
   */
  private function invocations(string $tag): int {
    return (int) \Drupal::state()->get('ai_test.counting_stub.' . $tag, 0);
  }

}
