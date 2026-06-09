<?php

namespace Drupal\Tests\ai\Unit\OperationType\SpeechToText;

use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\SpeechToText\SpeechToTextInput
 */
class SpeechToTextOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test', $output->getNormalized());
    $this->assertEquals('test', $output->getRawOutput());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\SpeechToText\SpeechToTextInput
   *   The output.
   */
  public function getOutput(): SpeechToTextOutput {
    return new SpeechToTextOutput('test', 'test', []);
  }

}
