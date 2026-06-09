<?php

namespace Drupal\Tests\ai\Unit\Event;

use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the event function works.
 *
 * @group ai
 * @covers \Drupal\ai\Event\PostGenerateResponseEvent
 */
class PostGenerateResponseEventTest extends TestCase {

  /**
   * Test getProviderid.
   */
  public function testGetProviderId(): void {
    $event = $this->getEvent();
    $this->assertEquals('test', $event->getProviderId());
  }

  /**
   * Test getConfiguration.
   */
  public function testGetConfiguration(): void {
    $event = $this->getEvent();
    $this->assertEquals([
      'test' => 'testing',
    ], $event->getConfiguration());
  }

  /**
   * Test get operation type.
   */
  public function testGetOperationType(): void {
    $event = $this->getEvent();
    $this->assertEquals('chat', $event->getOperationType());
  }

  /**
   * Test get model id.
   */
  public function testGetModelId(): void {
    $event = $this->getEvent();
    $this->assertEquals('model1', $event->getModelId());
  }

  /**
   * Test get input.
   */
  public function testGetInput(): void {
    $event = $this->getEvent();
    $this->assertEquals('This is a test', $event->getInput());
  }

  /**
   * Test get output.
   */
  public function testGetOutput(): void {
    $event = $this->getEvent();
    $this->assertEquals('It sure is!', $event->getOutput()->getNormalized()->getText());
  }

  /**
   * Test get tags.
   */
  public function testGetTags(): void {
    $event = $this->getEvent();
    $this->assertEquals(['ai-test'], $event->getTags());
  }

  /**
   * Test get debug data.
   */
  public function testGetDebugData(): void {
    $event = $this->getEvent();
    $this->assertEquals([
      'streamed' => TRUE,
    ], $event->getDebugData());
  }

  /**
   * Test set debug data.
   */
  public function testSetDebugData(): void {
    $event = $this->getEvent();
    $event->setDebugData('streamed', FALSE);
    $this->assertEquals([
      'streamed' => FALSE,
    ], $event->getDebugData());
  }

  /**
   * Test set configuration.
   */
  public function testSetConfiguration(): void {
    $event = $this->getEvent();
    $event->setConfiguration([
      'test' => 'tested',
    ]);
    $this->assertEquals([
      'test' => 'tested',
    ], $event->getConfiguration());
  }

  /**
   * Test set output.
   */
  public function testSetOutput(): void {
    $event = $this->getEvent();
    $output = new ChatOutput(
      new ChatMessage('Assistant', 'It is not!'),
      [
        'text' => 'It is not!',
      ],
      [],
    );
    $event->setOutput($output);
    $this->assertEquals($output, $event->getOutput());
  }

  /**
   * Test get event id.
   */
  public function testEventId(): void {
    $event = $this->getEvent();
    $this->assertEquals('unique_id', $event->getRequestThreadId());
  }

  /**
   * Helper function to get the events.
   *
   * @return \Drupal\ai\Event\PostGenerateResponseEvent|\PHPUnit\Framework\MockObject\MockObject
   *   The event.
   */
  public function getEvent(): PostGenerateResponseEvent {
    $output = new ChatOutput(
      new ChatMessage('Assistant', 'It sure is!'),
      [
        'text' => 'It sure is!',
      ],
      [],
    );
    return new PostGenerateResponseEvent('unique_id', 'test', 'chat', [
      'test' => 'testing',
    ],
      'This is a test',
      'model1',
      $output,
      ['ai-test'],
      [
        'streamed' => TRUE,
      ],
    );
  }

}
