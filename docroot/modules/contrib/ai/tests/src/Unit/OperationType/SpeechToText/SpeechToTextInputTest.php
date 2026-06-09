<?php

namespace Drupal\Tests\ai\Unit\OperationType\SpeechToText;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\SpeechToText\SpeechToTextInput
 */
class SpeechToTextInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('test.mp3', $input->getFile()->getFileName());
    $file = new AudioFile('bla', 'audio/mpeg', 'test2.mp3');
    $input->setFile($file);
    $this->assertEquals('test2.mp3', $input->getFile()->getFilename());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\SpeechToText\SpeechToTextInput
   *   The input.
   */
  public function getInput(): SpeechToTextInput {
    $file = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new SpeechToTextInput($file);
  }

}
