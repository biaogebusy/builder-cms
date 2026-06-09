<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\ai_observability\AiObservabilityUtils
 *
 * @group ai_observability
 */
class AiObservabilityUtilsTest extends TestCase {

  /**
   * @covers ::aiOutputToString
   */
  public function testAiOutputToStringWithChatOutputAndChatMessage(): void {
    $message = $this->createMock(ChatMessage::class);
    $message->method('getRole')->willReturn('user');
    $message->method('getText')->willReturn('Hello!');
    $message->method('getFiles')->willReturn([]);

    $output = $this->createMock(ChatOutput::class);
    $output->method('getNormalized')->willReturn($message);

    $result = AiObservabilityUtils::aiOutputToString($output);
    $this->assertSame('user: Hello!', $result);
  }

  /**
   * @covers ::aiOutputToString
   */
  public function testAiOutputToStringWithUnsupportedOutput(): void {
    $output = new class () {};
    $result = AiObservabilityUtils::aiOutputToString($output);
    $this->assertStringContainsString('not supported for string conversion', $result);
  }

  /**
   * @covers ::chatMessageToString
   */
  public function testChatMessageToStringWithFiles(): void {
    // Use a stub class with getFileName() method for file mocks.
    $fileStub1 = new class {

      /**
       * Returns the file name for the stub file object.
       *
       * @return string
       *   The file name.
       */
      public function getFileName() {
        return 'file1.txt';
      }

    };
    $fileStub2 = new class {

      /**
       * Returns the file name for the stub file object.
       *
       * @return string
       *   The file name.
       */
      public function getFileName() {
        return 'file2.txt';
      }

    };

    $message = $this->createMock(ChatMessage::class);
    $message->method('getRole')->willReturn('assistant');
    $message->method('getText')->willReturn('Here are your files.');
    $message->method('getFiles')->willReturn([$fileStub1, $fileStub2]);

    $result = AiObservabilityUtils::chatMessageToString($message);
    $this->assertSame('assistant: Here are your files. [Files: file1.txt, file2.txt]', $result);
  }

  /**
   * @covers ::chatMessageToString
   */
  public function testChatMessageToStringWithoutFiles(): void {
    $message = $this->createMock(ChatMessage::class);
    $message->method('getRole')->willReturn('system');
    $message->method('getText')->willReturn('System message.');
    $message->method('getFiles')->willReturn([]);

    $result = AiObservabilityUtils::chatMessageToString($message);
    $this->assertSame('system: System message.', $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataNoTruncation(): void {
    $payload = 'Short payload.';
    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, 100);
    $this->assertSame($payload, $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataWithTruncation(): void {
    $payload = str_repeat('A', 200);
    $maxLength = 50;
    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, $maxLength);
    $this->assertSame($maxLength, strlen($result));
    $this->assertStringContainsString('...', $result);
  }

}
