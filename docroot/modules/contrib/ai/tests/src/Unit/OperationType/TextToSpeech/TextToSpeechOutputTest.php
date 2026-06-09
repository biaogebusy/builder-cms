<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextToSpeech;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\TextToSpeech\TextToSpeechOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput
 */
class TextToSpeechOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test.mp3', $output->getNormalized()[0]->getFileName());
    $this->assertEquals('bla', $output->getRawOutput());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\TextToSpeech\TextToSpeechInput
   *   The output.
   */
  public function getOutput(): TextToSpeechOutput {
    $audio = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new TextToSpeechOutput([$audio], 'bla', []);
  }

}
