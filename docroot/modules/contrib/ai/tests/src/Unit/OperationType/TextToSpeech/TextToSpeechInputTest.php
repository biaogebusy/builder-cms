<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextToSpeech;

use Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput
 */
class TextToSpeechInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('Something', $input->getText());
    $input->setText('Something2');
    $this->assertEquals('Something2', $input->getText());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput
   *   The input.
   */
  public function getInput(): TextToSpeechInput {
    return new TextToSpeechInput('Something');
  }

}
