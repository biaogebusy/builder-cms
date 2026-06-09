<?php

namespace Drupal\Tests\ai\Unit\OperationType\SpeechToSpeech;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput
 */
class SpeechToSpeechInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('test.mp3', $input->toString());
    $file = new AudioFile('bla', 'audio/mpeg', 'test2.mp3');
    $input->setAudioFile($file);
    $this->assertEquals('test2.mp3', $input->toString());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput
   *   The input.
   */
  public function getInput(): SpeechToSpeechInput {
    $file = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new SpeechToSpeechInput($file);
  }

}
