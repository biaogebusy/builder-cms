<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageToVideo;

use Drupal\ai\OperationType\GenericType\VideoFile;
use Drupal\ai\OperationType\ImageToVideo\ImageToVideoOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageToVideo\ImageToVideoInput
 */
class ImageToVideoOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test.mp4', $output->getNormalized()[0]->getFileName());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageToVideo\ImageToVideoInput
   *   The output.
   */
  public function getOutput(): ImageToVideoOutput {
    $video = new VideoFile('bla', 'video/mp4', 'test.mp4');
    return new ImageToVideoOutput([$video], [$video], []);
  }

}
