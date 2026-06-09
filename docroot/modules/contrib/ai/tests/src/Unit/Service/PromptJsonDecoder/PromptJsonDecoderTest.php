<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service\PromptJsonDecoder;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoder
 * @group ai
 */
class PromptJsonDecoderTest extends UnitTestCase {

  /**
   * Tests messages with or without JSON in them.
   *
   * @param string $message
   *   The message to test.
   * @param int $placements
   *   The number of placements.
   * @param bool $json_exist
   *   If json exist.
   *
   * @dataProvider messageProvider
   *
   * @return void
   *   Nothing.
   */
  public function testJsonMessage(string $message, int $placements, bool $json_exist): void {
    $prompt_json_decoder = new PromptJsonDecoder();
    // Set the event dispatcher.
    $mock_event_dispatcher = $this->createMock(EventDispatcherInterface::class);

    // Optionally define behavior or expectations:
    $mock_event_dispatcher
      ->method('dispatch');
    // First test as a normal message.
    $chat_message = new ChatMessage('assistant', $message, []);
    $decoded = $prompt_json_decoder->decode($chat_message);
    if ($json_exist) {
      $this->assertIsArray($decoded);
    }
    else {
      $this->assertInstanceOf(ChatMessage::class, $decoded);
    }
  }

  /**
   * Provides chat messages, expected token where json starts and if json exist.
   *
   * @return array
   *   Message, token placement and if json exist.
   */
  public static function messageProvider(): array {
    $messages = [];
    $dir = __DIR__ . '/../../../../assets/test-prompts/prompt-json-decoder/';
    foreach (scandir($dir) as $file) {
      if (is_file($dir . $file)) {
        $messages[] = array_values(Yaml::parseFile($dir . $file));
      }
    }
    return $messages;
  }

}
