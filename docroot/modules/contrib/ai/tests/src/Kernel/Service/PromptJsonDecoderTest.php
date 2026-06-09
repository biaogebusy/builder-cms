<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ReplayedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessage;

/**
 * Tests the PromptJsonDecoder service.
 *
 * @group ai
 */
class PromptJsonDecoderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai'];

  /**
   * The PromptJsonDecoder instance to test.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder
   */
  protected $decoder;

  /**
   * Sets up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->decoder = $this->container->get('ai.prompt_json_decode');
  }

  /**
   * Test decoding a normal ChatMessage.
   */
  public function testDecodeNormalChatMessage(): void {
    // Create a normal ChatMessage with valid role and text.
    // Example role.
    $role = 'user';
    $text = '{"key": "value"}';

    $payload = new ChatMessage($role, $text);
    $decoded = $this->decoder->decode($payload);

    $this->assertIsArray($decoded);
    $this->assertEquals(['key' => 'value'], $decoded);
  }

  /**
   * Test decoding a streaming message with valid JSON.
   */
  public function testDecodeStreamingMessageWithValidJson(): void {
    // Create an iterator for the ReplayedChatMessageIterator.
    $iterator = new \ArrayObject([
      new StreamedChatMessage('user', '```\n'),
      new StreamedChatMessage('assistant', '{"key": "value"}\n```'),
    ]);

    $streamed_iterator = new ReplayedChatMessageIterator($iterator);
    // Set the first message before getting the iterator.
    $streamed_iterator->setFirstMessage('This is a header\n');

    $decoded = $this->decoder->decode($streamed_iterator);

    $this->assertIsArray($decoded);
    $this->assertEquals(['key' => 'value'], $decoded);
  }

  /**
   * Test decoding a streaming message without valid JSON.
   */
  public function testDecodeStreamingMessageWithoutValidJson(): void {
    // Create an iterator for the ReplayedChatMessageIterator.
    $iterator = new \ArrayObject([
      new StreamedChatMessage('user', '```\n'),
      new StreamedChatMessage('assistant', '{"key": "value", malformed}\n```'),
    ]);

    $streamed_iterator = new ReplayedChatMessageIterator($iterator);
    // Set the first message before getting the iterator.
    $streamed_iterator->setFirstMessage('This is a header\n');

    $decoded = $this->decoder->decode($streamed_iterator);
    // Check if decoded is an instance of ReplayedChatMessageIterator.
    $this->assertInstanceOf(ReplayedChatMessageIterator::class, $decoded);
  }

  /**
   * Test decoding an empty streaming message.
   */
  public function testDecodeEmptyStreamingMessage(): void {
    // Create an iterator for the ReplayedChatMessageIterator.
    $iterator = new \ArrayObject([
      new StreamedChatMessage('user', '```\n'),
    ]);

    $streamed_iterator = new ReplayedChatMessageIterator($iterator);
    // Set the first message before getting the iterator.
    $streamed_iterator->setFirstMessage('This is a header\n');

    $decoded = $this->decoder->decode($streamed_iterator);
    // Check if decoded is an instance of ReplayedChatMessageIterator.
    $this->assertInstanceOf(ReplayedChatMessageIterator::class, $decoded);

  }

}
