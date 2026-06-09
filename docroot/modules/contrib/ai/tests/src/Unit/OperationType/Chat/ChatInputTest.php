<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\ChatInput
 */
class ChatInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather today?', $input->getMessages()[0]->getText());
    $input->setMessages([new ChatMessage('user', 'What is the weather tomorrow?')]);
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather tomorrow?', $input->getMessages()[0]->getText());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Chat\ChatInput
   *   The input.
   */
  public function getInput(): ChatInput {
    $messages[] = new ChatMessage('user', 'What is the weather today?');
    return new ChatInput($messages);
  }

}
