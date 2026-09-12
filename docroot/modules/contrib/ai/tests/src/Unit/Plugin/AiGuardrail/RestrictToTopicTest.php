<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\AiGuardrail;

use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\Plugin\AiGuardrail\RestrictToTopic;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pre-LLM exit paths in the RestrictToTopic guardrail.
 *
 * The classifier branch is exercised through integration tests because it
 * requires a real AI provider. The selection logic is covered by
 * \Drupal\Tests\ai\Unit\Guardrail\UserMessageSelectionTraitTest.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\RestrictToTopic
 */
class RestrictToTopicTest extends TestCase {

  /**
   * Builds a configured RestrictToTopic instance.
   */
  protected function createGuardrail(array $configuration): RestrictToTopic {
    return new RestrictToTopic(
      $configuration,
      'restrict_to_topic',
      ['label' => 'Restrict to Topic'],
      $this->createStub(PromptJsonDecoderInterface::class),
    );
  }

  /**
   * Non-chat input short-circuits to a pass before any LLM call.
   */
  public function testNonChatInputPasses(): void {
    $guardrail = $this->createGuardrail([
      'valid_topics' => 'sports',
      'invalid_topics' => 'politics',
    ]);
    $input = new EmbeddingsInput('some text');

    $this->assertInstanceOf(PassResult::class, $guardrail->processInput($input));
  }

  /**
   * Chat input with no user messages short-circuits to a pass.
   */
  public function testNoUserMessagesPasses(): void {
    $guardrail = $this->createGuardrail([
      'valid_topics' => 'sports',
      'invalid_topics' => 'politics',
    ]);
    $input = new ChatInput([
      new ChatMessage('assistant', 'hello'),
      new ChatMessage('tool', 'result'),
    ]);

    $this->assertInstanceOf(PassResult::class, $guardrail->processInput($input));
  }

}
