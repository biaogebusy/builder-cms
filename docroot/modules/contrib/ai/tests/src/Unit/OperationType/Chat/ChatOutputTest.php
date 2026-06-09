<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\Dto\ChatProviderLimitsDto;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\ChatOutput
 */
class ChatOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('assistant', $output->getNormalized()->getRole());
    $this->assertEquals('That is great!', $output->getNormalized()->getText());
    $this->assertEquals([], $output->getMetadata());
  }

  /**
   * Tests rate limit serialization and round-tripping.
   */
  public function testRateLimitRoundTrip(): void {
    $output = $this->getOutput();
    $output->setRateLimits(new ChatProviderLimitsDto(
      rateLimitMaxRequests: 11,
      rateLimitRemainingRequests: 10,
      rateLimitResetRequests: 5,
    ));

    $array = $output->toArray();
    $this->assertArrayHasKey('rateLimits', $array);
    $this->assertSame(11, $array['rateLimits']['rateLimitMaxRequests']);

    $round_trip = ChatOutput::fromArray($array);
    $this->assertSame(11, $round_trip->getRateLimits()->rateLimitMaxRequests);
    $this->assertSame(10, $round_trip->getRateLimits()->rateLimitRemainingRequests);
    $this->assertSame(5, $round_trip->getRateLimits()->rateLimitResetRequests);
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Chat\ChatInput
   *   The output.
   */
  public function getOutput(): ChatOutput {
    $output = new ChatMessage('assistant', 'That is great!');
    return new ChatOutput($output, [
      'role' => 'assistant',
      'message' => 'That is great!',
    ], []);
  }

}
