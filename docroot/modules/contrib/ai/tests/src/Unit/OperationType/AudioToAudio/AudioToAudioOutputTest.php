<?php

namespace Drupal\Tests\ai\Unit\OperationType\AudioToAudio;

use Drupal\ai\OperationType\AudioToAudio\AudioToAudioOutput;
use Drupal\ai\OperationType\GenericType\AudioFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput
 */
class AudioToAudioOutputTest extends TestCase {

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
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput
   *   The output.
   */
  public function getOutput(): AudioToAudioOutput {
    $file = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new AudioToAudioOutput([$file], 'bla', []);
  }

}
