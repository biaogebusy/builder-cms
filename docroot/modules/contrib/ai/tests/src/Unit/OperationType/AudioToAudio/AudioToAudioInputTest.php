<?php

namespace Drupal\Tests\ai\Unit\OperationType\AudioToAudio;

use Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput
 */
class AudioToAudioInputTest extends TestCase {

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
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput
   *   The Input.
   */
  public function getInput(): AudioToAudioInput {
    $file = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new AudioToAudioInput($file);
  }

}
