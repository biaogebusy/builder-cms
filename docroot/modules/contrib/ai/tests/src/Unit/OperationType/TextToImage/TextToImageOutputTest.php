<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextToImage;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextToImage\TextToImageInput
 */
class TextToImageOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test.png', $output->getNormalized()[0]->getFileName());
    $this->assertEquals('bla', $output->getRawOutput());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\TextToImage\TextToImageInput
   *   The output.
   */
  public function getOutput(): TextToImageOutput {
    $image = new ImageFile('bla', 'image/png', 'test.png');
    return new TextToImageOutput([$image], 'bla', []);
  }

}
