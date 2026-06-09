<?php

namespace Drupal\Tests\ai\Unit\OperationType\Moderation;

use Drupal\ai\OperationType\Moderation\ModerationInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Moderation\ModerationInput
 */
class ModerationInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('Something', $input->getPrompt());
    $input->setPrompt('Something2');
    $this->assertEquals('Something2', $input->getPrompt());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Moderation\ModerationInput
   *   The input.
   */
  public function getInput(): ModerationInput {
    return new ModerationInput('Something');
  }

}
