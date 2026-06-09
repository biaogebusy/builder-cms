<?php

namespace Drupal\Tests\ai\Unit\OperationType\Moderation;

use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\Moderation\ModerationResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Moderation\ModerationInput
 */
class ModerationOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals(TRUE, $output->getNormalized()->isFlagged());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Moderation\ModerationInput
   *   The output.
   */
  public function getOutput(): ModerationOutput {
    $moderation = new ModerationResponse(TRUE, [
      'test' => 5,
    ]);
    return new ModerationOutput($moderation, [TRUE], []);
  }

}
