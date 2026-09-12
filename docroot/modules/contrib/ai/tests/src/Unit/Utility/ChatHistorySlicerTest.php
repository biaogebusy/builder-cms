<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Utility;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Utility\ChatHistorySlicer;

/**
 * Tests slicing chat histories without breaking tool call pairs.
 *
 * @coversDefaultClass \Drupal\ai\Utility\ChatHistorySlicer
 * @group ai
 */
class ChatHistorySlicerTest extends UnitTestCase {

  /**
   * Tests that nothing is returned when there is nothing to keep.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testEmptyResults(): void {
    $history = [
      new ChatMessage('user', 'Hello'),
      new ChatMessage('assistant', 'Hi there'),
    ];
    $this->assertSame([], ChatHistorySlicer::sliceLastToolLoops([], 5));
    $this->assertSame([], ChatHistorySlicer::sliceLastToolLoops($history, 0));
    $this->assertSame([], ChatHistorySlicer::sliceLastToolLoops($history, -3));
  }

  /**
   * Tests that a history without tool calls is sliced by message count.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testHistoryWithoutToolCalls(): void {
    $history = [];
    for ($i = 0; $i < 6; $i++) {
      $history[] = new ChatMessage($i % 2 === 0 ? 'user' : 'assistant', 'Message ' . $i);
    }
    $this->assertSame(array_slice($history, -3), ChatHistorySlicer::sliceLastToolLoops($history, 3));
    $this->assertSame($history, ChatHistorySlicer::sliceLastToolLoops($history, 20));
  }

  /**
   * Tests that a tool loop with parallel tool calls is kept in one piece.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testParallelToolCallsAreKeptTogether(): void {
    $user = new ChatMessage('user', 'Create three articles.');
    $call = self::toolCall(['call_1', 'call_2']);
    $first_result = self::toolResult('call_1');
    $second_result = self::toolResult('call_2');
    $history = [$user, $call, $first_result, $second_result];

    $sliced = ChatHistorySlicer::sliceLastToolLoops($history, 1);

    $this->assertSame([$call, $first_result, $second_result], $sliced);
    $this->assertNoOrphans($sliced);
  }

  /**
   * Tests that a tool call without any results is never kept.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testUnansweredToolCallIsDropped(): void {
    $call = self::toolCall(['call_1']);
    $result = self::toolResult('call_1');
    $dangling = self::toolCall(['call_2']);
    $history = [new ChatMessage('user', 'Go'), $call, $result, $dangling];

    $this->assertSame([$call, $result], ChatHistorySlicer::sliceLastToolLoops($history, 1));
    foreach (range(0, count($history) + 2) as $loops) {
      $sliced = ChatHistorySlicer::sliceLastToolLoops($history, $loops);
      $this->assertNotContains($dangling, $sliced, 'An unanswered tool call was kept.');
      $this->assertNoOrphans($sliced);
    }
  }

  /**
   * Tests that a tool call that is only partly answered is never kept.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testPartlyAnsweredToolCallIsDropped(): void {
    $text = new ChatMessage('assistant', 'Working on it.');
    $call = self::toolCall(['call_1', 'call_2']);
    $history = [$text, $call, self::toolResult('call_1')];

    $this->assertSame([$text], ChatHistorySlicer::sliceLastToolLoops($history, 5));
  }

  /**
   * Tests that a tool result without a tool call in front of it is dropped.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testOrphanedToolResultIsDropped(): void {
    $assistant = new ChatMessage('assistant', 'Done.');
    $user = new ChatMessage('user', 'Thanks');
    $history = [self::toolResult('call_1'), $assistant, $user];

    $this->assertSame([$assistant, $user], ChatHistorySlicer::sliceLastToolLoops($history, 3));
  }

  /**
   * Tests that a tool result without a tool id is still paired by adjacency.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testToolResultWithoutToolId(): void {
    $call = self::toolCall(['call_1']);
    $result = self::toolResult('');
    $this->assertSame([$call, $result], ChatHistorySlicer::sliceLastToolLoops([$call, $result], 1));

    $this->assertSame([], ChatHistorySlicer::sliceLastToolLoops([$result], 1));
  }

  /**
   * Tests that an empty list of tool calls is not treated as a tool loop.
   *
   * @covers ::hasToolCalls
   * @covers ::isStandalone
   */
  public function testEmptyToolsArrayIsNotTreatedAsToolCall(): void {
    $message = new ChatMessage('assistant', 'No tools used.');
    $message->setTools([]);

    $this->assertFalse(ChatHistorySlicer::hasToolCalls($message));
    $this->assertTrue(ChatHistorySlicer::isStandalone($message));
    $this->assertSame([$message], ChatHistorySlicer::sliceLastToolLoops([$message], 1));
  }

  /**
   * Tests that a message can not both answer and request tool calls.
   *
   * @covers ::groupIntoBlocks
   */
  public function testHybridToolMessageIsDropped(): void {
    $user = new ChatMessage('user', 'Go');
    $call = self::toolCall(['call_1']);
    $hybrid = self::toolCall(['call_2']);
    $hybrid->setRole('tool');
    $hybrid->setToolsId('call_1');
    $history = [$user, $call, $hybrid];

    $this->assertSame([$user], ChatHistorySlicer::sliceLastToolLoops($history, 5));
  }

  /**
   * Tests that a message in between closes an open tool loop.
   *
   * @covers ::sliceLastToolLoops
   */
  public function testMessageInBetweenClosesTheLoop(): void {
    $user = new ChatMessage('user', 'And now the second one.');
    $second_call = self::toolCall(['call_2']);
    $second_result = self::toolResult('call_2');
    $history = [
      self::toolCall(['call_1']),
      self::toolResult('call_1'),
      $user,
      $second_call,
      $second_result,
    ];

    $this->assertSame(
      [$user, $second_call, $second_result],
      ChatHistorySlicer::sliceLastToolLoops($history, 2),
    );
  }

  /**
   * Tests that the summary is placed after the first message of the history.
   *
   * @covers ::sliceWithSummary
   */
  public function testSliceWithSummary(): void {
    $user = new ChatMessage('user', 'Create three articles.');
    $call = self::toolCall(['call_1', 'call_2']);
    $first_result = self::toolResult('call_1');
    $second_result = self::toolResult('call_2');
    $summary = new ChatMessage('assistant', '<SummaryOfConversation></SummaryOfConversation>');
    $history = [$user, $call, $first_result, $second_result];

    $this->assertSame(
      [$user, $summary, $call, $first_result, $second_result],
      ChatHistorySlicer::sliceWithSummary($history, $summary, 1),
    );
    $this->assertSame([$user, $summary], ChatHistorySlicer::sliceWithSummary($history, $summary, 0));
  }

  /**
   * Tests that the first message of the history is never repeated.
   *
   * @covers ::sliceWithSummary
   */
  public function testSliceWithSummaryDoesNotRepeatTheFirstMessage(): void {
    $user = new ChatMessage('user', 'Hello');
    $assistant = new ChatMessage('assistant', 'Hi there');
    $summary = new ChatMessage('assistant', '<SummaryOfConversation></SummaryOfConversation>');

    $this->assertSame(
      [$user, $summary, $assistant],
      ChatHistorySlicer::sliceWithSummary([$user, $assistant], $summary, 5),
    );
  }

  /**
   * Tests that a first message that needs a tool call pair is not moved.
   *
   * @covers ::sliceWithSummary
   */
  public function testSliceWithSummarySkipsToolCallingFirstMessage(): void {
    $call = self::toolCall(['call_1']);
    $result = self::toolResult('call_1');
    $assistant = new ChatMessage('assistant', 'Done.');
    $summary = new ChatMessage('assistant', '<SummaryOfConversation></SummaryOfConversation>');
    $history = [$call, $result, $assistant];

    $sliced = ChatHistorySlicer::sliceWithSummary($history, $summary, 1);

    $this->assertSame([$summary, $assistant], $sliced);
    $this->assertNoOrphans($sliced);
  }

  /**
   * Tests that no amount of loops to keep can produce a broken history.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $history
   *   The chat history to slice.
   *
   * @dataProvider historyProvider
   *
   * @covers ::sliceLastToolLoops
   * @covers ::sliceWithSummary
   */
  public function testToolCallsAreAlwaysPaired(array $history): void {
    $summary = new ChatMessage('assistant', '<SummaryOfConversation></SummaryOfConversation>');
    foreach (range(0, count($history) + 2) as $loops) {
      $this->assertNoOrphans(ChatHistorySlicer::sliceLastToolLoops($history, $loops));
      $this->assertNoOrphans(ChatHistorySlicer::sliceWithSummary($history, $summary, $loops));
    }
  }

  /**
   * Provides chat histories that mix tool calls with other messages.
   *
   * @return array
   *   The chat histories, keyed by what they describe.
   */
  public static function historyProvider(): array {
    $hybrid = self::toolCall(['call_9']);
    $hybrid->setRole('tool');
    $hybrid->setToolsId('call_8');

    return [
      'no tool calls' => [
        [
          new ChatMessage('user', 'Hello'),
          new ChatMessage('assistant', 'Hi there'),
          new ChatMessage('user', 'Bye'),
        ],
      ],
      'parallel tool calls' => [
        [
          new ChatMessage('user', 'Create three articles.'),
          self::toolCall(['call_1', 'call_2']),
          self::toolResult('call_1'),
          self::toolResult('call_2'),
          new ChatMessage('assistant', 'All done.'),
        ],
      ],
      'several tool loops' => [
        [
          new ChatMessage('user', 'Go'),
          self::toolCall(['call_1']),
          self::toolResult('call_1'),
          self::toolCall(['call_2', 'call_3']),
          self::toolResult('call_2'),
          self::toolResult('call_3'),
          self::toolCall(['call_4']),
          self::toolResult('call_4'),
        ],
      ],
      'unanswered tool call at the end' => [
        [
          new ChatMessage('user', 'Go'),
          self::toolCall(['call_1']),
          self::toolResult('call_1'),
          self::toolCall(['call_2']),
        ],
      ],
      'partly answered tool call' => [
        [
          new ChatMessage('user', 'Go'),
          self::toolCall(['call_1', 'call_2']),
          self::toolResult('call_1'),
        ],
      ],
      'orphaned tool result at the start' => [
        [
          self::toolResult('call_1'),
          self::toolResult('call_2'),
          new ChatMessage('assistant', 'Done.'),
        ],
      ],
      'tool results without tool ids' => [
        [
          new ChatMessage('user', 'Go'),
          self::toolCall(['call_1', 'call_2']),
          self::toolResult(''),
          self::toolResult(''),
        ],
      ],
      'a message that answers and requests tool calls' => [
        [
          new ChatMessage('user', 'Go'),
          self::toolCall(['call_8']),
          $hybrid,
          new ChatMessage('assistant', 'Done.'),
        ],
      ],
    ];
  }

  /**
   * Builds an assistant message that requests tool calls.
   *
   * @param string[] $tool_ids
   *   The tool call ids to request.
   * @param string $text
   *   The text of the message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The assistant message.
   */
  protected static function toolCall(array $tool_ids, string $text = 'Let me look that up.'): ChatMessage {
    $tools = [];
    foreach ($tool_ids as $tool_id) {
      $tool = new ToolsFunctionOutput(NULL, $tool_id);
      // The name is typed and has no default, so it has to be set here.
      $tool->setName('test_function');
      $tools[] = $tool;
    }
    $message = new ChatMessage('assistant', $text);
    $message->setTools($tools);
    return $message;
  }

  /**
   * Builds a message that holds the result of a tool call.
   *
   * @param string $tool_id
   *   The tool call id that is answered, or an empty string for none.
   * @param string $text
   *   The text of the message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The tool message.
   */
  protected static function toolResult(string $tool_id, string $text = 'The result.'): ChatMessage {
    $message = new ChatMessage('tool', $text);
    if ($tool_id !== '') {
      $message->setToolsId($tool_id);
    }
    return $message;
  }

  /**
   * Asserts that every tool call in a chat history is properly paired.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The chat history to check.
   */
  protected function assertNoOrphans(array $messages): void {
    $open = NULL;
    foreach ($messages as $delta => $message) {
      if (ChatHistorySlicer::isToolResult($message)) {
        $this->assertFalse(
          ChatHistorySlicer::hasToolCalls($message),
          "The message at index $delta both answers and requests tool calls.",
        );
        $this->assertNotNull($open, "The tool result at index $delta has no tool call in front of it.");
        $open['answers']++;
        continue;
      }
      if ($open !== NULL) {
        $this->assertGreaterThanOrEqual($open['requests'], $open['answers'], 'A tool call was not answered.');
      }
      $open = ChatHistorySlicer::hasToolCalls($message)
        ? ['requests' => count($message->getTools()), 'answers' => 0]
        : NULL;
    }
    if ($open !== NULL) {
      $this->assertGreaterThanOrEqual($open['requests'], $open['answers'], 'The last tool call was not answered.');
    }
    // A sliced history always has sequential keys.
    $this->assertSame(array_values($messages), $messages, 'The chat history is not a list.');
  }

}
