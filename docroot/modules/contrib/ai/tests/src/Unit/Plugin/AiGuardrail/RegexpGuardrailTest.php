<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\AiGuardrail;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Plugin\AiGuardrail\RegexpGuardrail;
use Drupal\ai\Service\HostnameFilter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the RegexpGuardrail plugin.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\RegexpGuardrail
 */
class RegexpGuardrailTest extends TestCase {

  /**
   * Creates a RegexpGuardrail instance with the given configuration.
   *
   * @param string $pattern
   *   The regex pattern.
   * @param string $message
   *   The violation message.
   *
   * @return \Drupal\ai\Plugin\AiGuardrail\RegexpGuardrail
   *   The configured guardrail.
   */
  protected function createGuardrail(string $pattern, string $message = 'Blocked.'): RegexpGuardrail {
    $guardrail = new RegexpGuardrail(
      ['regexp_pattern' => $pattern, 'violation_message' => $message],
      'regexp_guardrail',
      ['label' => 'Regexp Guardrail'],
    );
    $guardrail->setConfiguration([
      'regexp_pattern' => $pattern,
      'violation_message' => $message,
    ]);
    return $guardrail;
  }

  /**
   * Tests processInput blocks matching text.
   */
  public function testProcessInputBlocksMatch(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $input = new ChatInput([
      new ChatMessage('user', 'My email is test@example.com'),
    ]);

    $result = $guardrail->processInput($input);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processInput passes non-matching text.
   */
  public function testProcessInputPassesCleanText(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $input = new ChatInput([
      new ChatMessage('user', 'Hello, how are you?'),
    ]);

    $result = $guardrail->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Tests processOutput blocks matching text in AI response.
   */
  public function testProcessOutputBlocksMatch(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $output = new ChatOutput(
      new ChatMessage('assistant', 'Contact us at support@example.com for help.'),
      [],
      [],
    );

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processOutput passes non-matching AI response.
   */
  public function testProcessOutputPassesCleanText(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $output = new ChatOutput(
      new ChatMessage('assistant', 'Here is the information you requested.'),
      [],
      [],
    );

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Tests processOutput handles streamed responses gracefully.
   */
  public function testProcessOutputSkipsStreamedOutput(): void {
    $guardrail = $this->createGuardrail('/test/');
    $streamed = $this->createStub(StreamedChatMessageIteratorInterface::class);
    $output = new ChatOutput($streamed, [], []);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Tests processOutput with empty pattern configuration.
   */
  public function testProcessOutputSkipsEmptyPattern(): void {
    $guardrail = $this->createGuardrail('');
    $output = new ChatOutput(
      new ChatMessage('assistant', 'test@example.com'),
      [],
      [],
    );

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Tests processOutput detects credit card numbers.
   */
  public function testProcessOutputBlocksCreditCard(): void {
    $guardrail = $this->createGuardrail('/(?<!\d)(?:\d[\s\-]?){12,19}\d(?!\d)/');
    $output = new ChatOutput(
      new ChatMessage('assistant', 'Your card number is 4111 1111 1111 1111.'),
      [],
      [],
    );

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processOutput blocks a regex match inside a tool call argument.
   */
  public function testProcessOutputBlocksMatchInToolArgument(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $output = $this->buildChatOutputWithToolArguments(['to' => 'support@example.com']);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processOutput passes when tool call arguments are clean.
   */
  public function testProcessOutputPassesCleanToolArguments(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $output = $this->buildChatOutputWithToolArguments(['city' => 'Berlin']);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Tests processOutput recurses into nested array tool argument values.
   */
  public function testProcessOutputBlocksMatchInNestedToolArgument(): void {
    $guardrail = $this->createGuardrail('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i');
    $output = $this->buildChatOutputWithToolArguments([
      'payload' => [
        'recipients' => [
          ['address' => 'support@example.com'],
        ],
      ],
    ]);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processOutput scans raw characters, not JSON-escaped forms.
   *
   * Regression guard for MR !1338 review feedback: the previous
   * implementation ran the regex against a JSON-encoded representation of
   * tool calls, which turned real control characters into backslash escape
   * sequences and hid patterns targeting newlines, tabs or quotes. The
   * current implementation walks argument values directly, so a pattern
   * matching a literal tab is triggered by a real tab in the argument
   * string.
   */
  public function testProcessOutputScansRawControlCharactersInToolArguments(): void {
    $guardrail = $this->createGuardrail('/\t/');
    $output = $this->buildChatOutputWithToolArguments([
      'body' => "line one\tline two",
    ]);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(StopResult::class, $result);
  }

  /**
   * Tests processOutput passes when the pattern only matches an escape form.
   *
   * Companion to the raw-character test: a pattern looking for the literal
   * two-character sequence backslash-t (as JSON encoding would produce for a
   * real tab) must NOT fire against a raw tab, because the flattener never
   * escapes the value.
   */
  public function testProcessOutputDoesNotMatchJsonEscapeForms(): void {
    $guardrail = $this->createGuardrail('/\\\\t/');
    $output = $this->buildChatOutputWithToolArguments([
      'body' => "line one\tline two",
    ]);

    $result = $guardrail->processOutput($output);
    $this->assertInstanceOf(PassResult::class, $result);
  }

  /**
   * Builds a ChatOutput whose normalized message carries a single tool call.
   *
   * @param array $arguments
   *   Tool call arguments keyed by argument name.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The constructed chat output.
   */
  protected function buildChatOutputWithToolArguments(array $arguments): ChatOutput {
    // ToolsPropertyResult::setValue() routes string values through the
    // hostname filter service from the Drupal container, so a stub container
    // must be in place before constructing the tool call.
    $hostname_filter = $this->createMock(HostnameFilter::class);
    $hostname_filter->method('filterText')
      ->willReturnCallback(fn (string $text): string => $text);
    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);

    $tool = new ToolsFunctionOutput(NULL, 'call_1', $arguments);
    $tool->setName('send_email');
    $message = new ChatMessage('assistant', '');
    $message->setTools([$tool]);
    return new ChatOutput($message, [], []);
  }

  /**
   * Tests processInput with empty pattern configuration.
   */
  public function testProcessInputSkipsEmptyPattern(): void {
    $guardrail = $this->createGuardrail('');
    $input = new ChatInput([
      new ChatMessage('user', 'test@example.com'),
    ]);

    $result = $guardrail->processInput($input);
    $this->assertInstanceOf(PassResult::class, $result);
  }

}
