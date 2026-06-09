<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageToImage;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageToImage\ImageToImageOutput
 */
class ImageToImageOutputTest extends TestCase {

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
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageToImage\ImageToImageOutput
   *   The output.
   */
  public function getOutput(): ImageToImageOutput {
    $image = new ImageFile('bla', 'image/png', 'test.png');
    return new ImageToImageOutput([$image], 'bla', []);
  }

}
