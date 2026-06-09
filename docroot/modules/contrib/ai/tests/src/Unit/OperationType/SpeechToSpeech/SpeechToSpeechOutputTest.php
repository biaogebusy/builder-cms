<?php

namespace Drupal\Tests\ai\Unit\OperationType\SpeechToSpeech;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput
 */
class SpeechToSpeechOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test.mp3', $output->getNormalized()[0]->getFileName());
    $this->assertEquals('bla', $output->getRawOutput());
    $this->assertEquals([], $output->getMetadata());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput
   *   The output.
   */
  public function getOutput(): SpeechToSpeechOutput {
    $file = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new SpeechToSpeechOutput([$file], 'bla', []);
  }

}
