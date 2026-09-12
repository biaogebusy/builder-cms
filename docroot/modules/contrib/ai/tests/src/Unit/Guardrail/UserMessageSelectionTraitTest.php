<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Guardrail;

use Drupal\ai\Guardrail\UserMessageSelectionTrait;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests the user message selection helper used by guardrail plugins.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Guardrail\UserMessageSelectionTrait
 */
class UserMessageSelectionTraitTest extends TestCase {

  /**
   * Returns an anonymous object that exposes the trait's protected method.
   */
  protected function selector(): object {
    return new class() {
      use UserMessageSelectionTrait;
      use StringTranslationTrait;

      /**
       * Minimal configuration expected by the trait form helper.
       *
       * @var array<string, mixed>
       */
      protected array $configuration = [];

      /**
       * Public proxy for the protected trait method.
       */
      public function call(ChatInput $input, bool $scan_all): array {
        return $this->selectUserMessages($input, $scan_all);
      }

    };
  }

  /**
   * Default mode returns the most recent user message.
   */
  public function testDefaultReturnsLastUserMessage(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'first'),
      new ChatMessage('assistant', 'reply'),
      new ChatMessage('user', 'second'),
    ]);

    $result = $this->selector()->call($input, FALSE);

    $this->assertCount(1, $result);
    $this->assertSame('second', $result[0]->getText());
  }

  /**
   * Default mode walks back past trailing non-user messages (the role guard).
   *
   * Reproduces the tool-use case: the user sends a prompt, the assistant
   * responds with a tool call, the tool result comes back as the most recent
   * message, and the next guardrail evaluation must still scan the original
   * user prompt instead of the tool result text.
   */
  public function testDefaultWalksBackPastToolResultMessages(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'send the report to test@example.com'),
      new ChatMessage('assistant', ''),
      new ChatMessage('tool', 'report sent successfully'),
    ]);

    $result = $this->selector()->call($input, FALSE);

    $this->assertCount(1, $result);
    $this->assertSame('send the report to test@example.com', $result[0]->getText());
  }

  /**
   * Scan-all mode returns every user message in original order.
   */
  public function testScanAllReturnsEveryUserMessage(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'first'),
      new ChatMessage('assistant', 'reply'),
      new ChatMessage('user', 'second'),
      new ChatMessage('tool', 'irrelevant'),
      new ChatMessage('user', 'third'),
    ]);

    $result = $this->selector()->call($input, TRUE);

    $this->assertCount(3, $result);
    $this->assertSame(['first', 'second', 'third'], array_map(
      static fn (ChatMessage $m): string => $m->getText(),
      $result,
    ));
  }

  /**
   * No user messages in the conversation returns an empty array.
   */
  public function testReturnsEmptyArrayWhenNoUserMessages(): void {
    $input = new ChatInput([
      new ChatMessage('assistant', 'hello'),
      new ChatMessage('tool', 'result'),
    ]);

    $this->assertSame([], $this->selector()->call($input, FALSE));
    $this->assertSame([], $this->selector()->call($input, TRUE));
  }

}
