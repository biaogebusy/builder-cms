<?php

namespace Drupal\Tests\ai\Unit\Event;

use Drupal\ai\Event\PreGenerateResponseEvent;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the event function works.
 *
 * @group ai
 * @covers \Drupal\ai\Event\PreGenerateResponseEvent
 */
class PreGenerateResponseEventTest extends TestCase {

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
   * Test get event id.
   */
  public function testEventId(): void {
    $event = $this->getEvent();
    $this->assertEquals('unique_id', $event->getRequestThreadId());
  }

  /**
   * Helper function to get the events.
   *
   * @return \Drupal\ai\Event\PreGenerateResponseEvent|\PHPUnit\Framework\MockObject\MockObject
   *   The event.
   */
  public function getEvent(): PreGenerateResponseEvent {
    return new PreGenerateResponseEvent(
      'unique_id',
      'test',
      'chat',
      [
        'test' => 'testing',
      ],
      'This is a test',
      'model1',
      ['ai-test'],
      [
        'streamed' => TRUE,
      ],
    );
  }

}
