<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\AiGuardrail;

use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\Plugin\AiGuardrail\InputLengthLimit;
use Drupal\ai\Utility\TokenizerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the InputLengthLimit guardrail plugin.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\InputLengthLimit
 */
class InputLengthLimitTest extends TestCase {

  /**
   * The mocked tokenizer.
   *
   * @var \Drupal\ai\Utility\TokenizerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected TokenizerInterface $tokenizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->tokenizer = $this->createMock(TokenizerInterface::class);
  }

  /**
   * Creates a plugin instance with the given configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\ai\Plugin\AiGuardrail\InputLengthLimit
   *   The plugin instance.
   */
  protected function createPlugin(array $configuration): InputLengthLimit {
    return new InputLengthLimit(
      $configuration,
      'input_length_limit',
      [
        'id' => 'input_length_limit',
        'label' => 'Input Length Limit',
      ],
      $this->tokenizer,
    );
  }

  /**
   * Test that non-chat input is passed through.
   */
  public function testNonChatInputPasses(): void {
    $plugin = $this->createPlugin(['max_length' => 10]);
    $input = new TextToImageInput('test');
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * Test that input within character limit passes.
   */
  public function testCharacterLimitPass(): void {
    $plugin = $this->createPlugin(['max_length' => 100]);
    $input = new ChatInput([new ChatMessage('user', 'Hello world')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * Test that input exceeding character limit is stopped.
   */
  public function testCharacterLimitStop(): void {
    $plugin = $this->createPlugin(['max_length' => 5]);
    $input = new ChatInput([new ChatMessage('user', 'Hello world')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertTrue($result->stop());
    $this->assertStringContainsString('11', $result->getMessage());
    $this->assertStringContainsString('5', $result->getMessage());
    $this->assertStringContainsString('characters', $result->getMessage());
  }

  /**
   * Test token-based counting pass.
   */
  public function testTokenLimitPass(): void {
    $this->tokenizer->expects($this->once())
      ->method('countTokens')
      ->with('Hello world')
      ->willReturn(2);
    $this->tokenizer->expects($this->once())
      ->method('setModel')
      ->with('gpt-4');

    $plugin = $this->createPlugin([
      'max_length' => 10,
      'use_tokens' => TRUE,
      'tokenizer_model' => 'gpt-4',
    ]);
    $input = new ChatInput([new ChatMessage('user', 'Hello world')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Test token-based counting stop.
   */
  public function testTokenLimitStop(): void {
    $this->tokenizer->expects($this->once())
      ->method('countTokens')
      ->with('Hello world this is a long message')
      ->willReturn(8);
    $this->tokenizer->expects($this->once())
      ->method('setModel')
      ->with('gpt-4');

    $plugin = $this->createPlugin([
      'max_length' => 5,
      'use_tokens' => TRUE,
      'tokenizer_model' => 'gpt-4',
    ]);
    $input = new ChatInput([new ChatMessage('user', 'Hello world this is a long message')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertStringContainsString('8', $result->getMessage());
    $this->assertStringContainsString('tokens', $result->getMessage());
  }

  /**
   * Test check all messages mode.
   */
  public function testCheckAllMessages(): void {
    $plugin = $this->createPlugin([
      'max_length' => 20,
      'check_all_messages' => TRUE,
    ]);
    $input = new ChatInput([
      new ChatMessage('user', 'Hello world'),
      new ChatMessage('assistant', 'Hi there!'),
      new ChatMessage('user', 'How are you?'),
    ]);
    // Combined: "Hello world\nHi there!\nHow are you?" = 35 chars.
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertTrue($result->stop());
  }

  /**
   * Test last message only mode (default) with multiple messages.
   */
  public function testLastMessageOnly(): void {
    $plugin = $this->createPlugin([
      'max_length' => 20,
    ]);
    $input = new ChatInput([
      new ChatMessage('user', 'This is a very long first message that exceeds limits'),
      new ChatMessage('user', 'Short'),
    ]);
    // Only checks "Short" (5 chars) - should pass.
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Test that no max_length configured skips the check.
   */
  public function testNoLimitConfigured(): void {
    $plugin = $this->createPlugin([]);
    $input = new ChatInput([new ChatMessage('user', 'Hello')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Test custom violation message with placeholders.
   */
  public function testCustomViolationMessage(): void {
    $plugin = $this->createPlugin([
      'max_length' => 5,
      'violation_message' => 'Too long: @count/@max @unit',
    ]);
    $input = new ChatInput([new ChatMessage('user', 'Hello world')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(StopResult::class, $result);
    $this->assertEquals('Too long: 11/5 characters', $result->getMessage());
  }

  /**
   * Test output processing always passes.
   */
  public function testOutputAlwaysPasses(): void {
    $plugin = $this->createPlugin(['max_length' => 5]);
    $output = $this->createMock(OutputInterface::class);
    $result = $plugin->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
    $this->assertFalse($result->stop());
  }

  /**
   * Test multibyte character counting.
   */
  public function testMultibyteCharacters(): void {
    $plugin = $this->createPlugin(['max_length' => 5]);
    // "Hello" is 5 characters.
    $input = new ChatInput([new ChatMessage('user', 'Hello')]);
    $result = $plugin->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);

    // "Helloo" is 6 characters - should stop.
    $input2 = new ChatInput([new ChatMessage('user', 'Helloo')]);
    $result2 = $plugin->processInput($input2);
    $this->assertInstanceOf(StopResult::class, $result2);
  }

}
