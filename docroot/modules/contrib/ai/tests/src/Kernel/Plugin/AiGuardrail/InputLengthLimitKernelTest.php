<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiGuardrail;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the InputLengthLimit guardrail in a full Drupal kernel context.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\InputLengthLimit
 */
class InputLengthLimitKernelTest extends KernelTestBase {

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

    // Create a guardrail entity wrapping the input_length_limit plugin.
    $guardrail = AiGuardrail::create([
      'id' => 'test_length_limit',
      'label' => 'Test Length Limit',
      'description' => 'Test guardrail for length limiting.',
      'guardrail' => 'input_length_limit',
      'guardrail_settings' => [
        'max_length' => 20,
        'use_tokens' => FALSE,
        'check_all_messages' => FALSE,
        'violation_message' => 'Too long: @count @unit exceeds @max @unit.',
      ],
    ]);
    $guardrail->save();

    // Create a guardrail set with the guardrail as a pre-generate guardrail.
    $guardrail_set = AiGuardrailSet::create([
      'id' => 'test_length_set',
      'label' => 'Test Length Set',
      'description' => 'Test guardrail set.',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => ['test_length_limit']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ]);
    $guardrail_set->save();
  }

  /**
   * Test that short input passes through the guardrail and reaches the AI.
   */
  public function testShortInputPasses(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'Short text'),
    ]);

    // Apply the guardrail set.
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_length_set', $input);

    // Make the AI call - should succeed.
    $result = $provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);
    $normalized = $result->getNormalized();
    // EchoAI returns "Hello world! Input: ..." for successful calls.
    $this->assertStringContainsString('Short text', $normalized->getText());
  }

  /**
   * Test that long input is blocked by the guardrail.
   */
  public function testLongInputBlocked(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', 'This is a very long message that definitely exceeds the twenty character limit.'),
    ]);

    // Apply the guardrail set.
    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_length_set', $input);

    // Make the AI call - should be intercepted by guardrail.
    $result = $provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);
    $normalized = $result->getNormalized();
    // The guardrail should have forced a stop message as the output.
    $this->assertStringContainsString('Too long', $normalized->getText());
    $this->assertStringContainsString('exceeds', $normalized->getText());

    // Verify guardrail results were recorded on the input.
    $guardrail_results = $input->getAllGuardrailResults();
    $this->assertNotEmpty($guardrail_results);
  }

  /**
   * Test the guardrail plugin can be loaded via the plugin manager.
   */
  public function testPluginDiscovery(): void {
    $plugin_manager = \Drupal::service('plugin.manager.ai_guardrail');
    $plugin = $plugin_manager->createInstance('input_length_limit', [
      'max_length' => 10,
    ]);
    $this->assertEquals('Input Length Limit', $plugin->label());
    $this->assertTrue($plugin->isAvailable());
  }

}
