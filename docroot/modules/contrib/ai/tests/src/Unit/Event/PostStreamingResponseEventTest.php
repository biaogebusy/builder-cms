<?php

namespace Drupal\Tests\ai\Unit\Event;

use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the event function works.
 *
 * @group ai
 * @covers \Drupal\ai\Event\PostStreamingResponseEvent
 */
class PostStreamingResponseEventTest extends TestCase {

  /**
   * Test get event id.
   */
  public function testEventId(): void {
    $event = $this->getEvent();
    $this->assertEquals('unique_id', $event->getRequestThreadId());
  }

  /**
   * Test get response.
   */
  public function testResponse(): void {
    $event = $this->getEvent();
    $this->assertEquals('It sure is!', $event->getOutput()->getNormalized()->getText());
  }

  /**
   * Helper function to get the events.
   *
   * @return \Drupal\ai\Event\PostStreamingResponseEvent|\PHPUnit\Framework\MockObject\MockObject
   *   The event.
   */
  public function getEvent(): PostStreamingResponseEvent {
    $output = new ChatOutput(
      new ChatMessage('Assistant', 'It sure is!'),
      [
        'text' => 'It sure is!',
      ],
      [],
    );
    return new PostStreamingResponseEvent('unique_id', 'test', 'chat', [
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
