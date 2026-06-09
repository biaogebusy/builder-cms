<?php

namespace Drupal\Tests\ai\Unit\Event;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\OperationType\OutputInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the AiExceptionEvent.
 *
 * @group ai
 * @covers \Drupal\ai\Event\AiExceptionEvent
 */
class AiExceptionEventTest extends TestCase {

  /**
   * With no subscriber touching it, the event round-trips the exception.
   */
  public function testNoSubscriberLeavesExceptionUntouched(): void {
    $original = new AiQuotaException('quota exceeded');
    $event = $this->getEvent($original);

    $this->assertSame($original, $event->getException());
    $this->assertSame('quota exceeded', $event->getMessage());
    $this->assertNull($event->getForcedOutputObject());
  }

  /**
   * Rewriting the message preserves the exception class for catch blocks.
   */
  public function testSetMessagePreservesExceptionClass(): void {
    $original = new AiQuotaException('Budget has been exceeded! Current cost: 1.133637, Max budget: 1.0');
    $event = $this->getEvent($original);

    $event->setMessage('Your AI budget has been reached. Please try again later.');

    $thrown = $event->getException();
    $this->assertNotSame($original, $thrown, 'A new exception is built when the message changes.');
    $this->assertInstanceOf(AiQuotaException::class, $thrown, 'The original class is preserved.');
    $this->assertSame('Your AI budget has been reached. Please try again later.', $thrown->getMessage());
    $this->assertSame($original, $thrown->getPrevious(), 'The original is chained as previous.');
  }

  /**
   * Different exception subclasses keep their identity after rewriting.
   */
  public function testSetMessagePreservesRateLimitClass(): void {
    $original = new AiRateLimitException('too many requests');
    $event = $this->getEvent($original);
    $event->setMessage('Please slow down.');

    $this->assertInstanceOf(AiRateLimitException::class, $event->getException());
    $this->assertSame('Please slow down.', $event->getMessage());
  }

  /**
   * Constructor-level custom message is honoured and preserves class.
   */
  public function testConstructorMessageOverride(): void {
    $original = new AiQuotaException('raw');
    $event = $this->getEvent($original, message: 'pretty');

    $this->assertSame('pretty', $event->getMessage());
    $this->assertInstanceOf(AiQuotaException::class, $event->getException());
    $this->assertSame('pretty', $event->getException()->getMessage());
  }

  /**
   * A subscriber can provide a forced output for graceful failover.
   */
  public function testForcedOutputObjectRoundTrip(): void {
    $event = $this->getEvent();
    $output = $this->createMock(OutputInterface::class);

    $event->setForcedOutputObject($output);

    $this->assertSame($output, $event->getForcedOutputObject());
  }

  /**
   * Forced output and message rewriting can coexist, allowing graceful fix.
   */
  public function testForcedOutputAndRewrittenMessageCoexist(): void {
    $event = $this->getEvent();
    $output = $this->createMock(OutputInterface::class);

    $event->setMessage('swapped');
    $event->setForcedOutputObject($output);

    $this->assertSame($output, $event->getForcedOutputObject());
    $this->assertSame('swapped', $event->getMessage());
    $this->assertInstanceOf(AiQuotaException::class, $event->getException());
  }

  /**
   * Request context is exposed via the inherited base-class getters.
   */
  public function testContextIsExposedViaBaseGetters(): void {
    $input = new \stdClass();
    $event = new AiExceptionEvent(
      exception: new AiQuotaException('quota'),
      requestThreadId: 'thread-123',
      providerId: 'openai',
      operationType: 'chat',
      configuration: ['temperature' => 0.5],
      input: $input,
      modelId: 'gpt-4o',
      tags: ['ai-test'],
      debugData: ['streamed' => TRUE],
    );

    $this->assertSame('thread-123', $event->getRequestThreadId());
    $this->assertSame('openai', $event->getProviderId());
    $this->assertSame('chat', $event->getOperationType());
    $this->assertSame('gpt-4o', $event->getModelId());
    $this->assertSame($input, $event->getInput());
    $this->assertSame(['temperature' => 0.5], $event->getConfiguration());
    $this->assertSame(['ai-test'], $event->getTags());
    $this->assertSame(['streamed' => TRUE], $event->getDebugData());
    $this->assertSame($event->exception, $event->getException(),
      'With no message rewrite, getException() returns the original instance.');
  }

  /**
   * Helper to build a minimal event for exception testing.
   */
  private function getEvent(
    ?\Exception $exception = NULL,
    string $message = '',
  ): AiExceptionEvent {
    return new AiExceptionEvent(
      exception: $exception ?? new AiQuotaException('quota'),
      requestThreadId: 'thread-test',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: NULL,
      modelId: 'gpt-4o',
      message: $message,
    );
  }

}
