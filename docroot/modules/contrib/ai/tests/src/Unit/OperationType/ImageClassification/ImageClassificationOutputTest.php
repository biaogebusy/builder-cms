<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageClassification;

use Drupal\ai\OperationType\ImageClassification\ImageClassificationItem;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput
 */
class ImageClassificationOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('test', $output->getNormalized()[0]->getLabel());
    $this->assertEquals(0.5, $output->getNormalized()[0]->getConfidenceScore());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageClassification\ImageClassificationInput
   *   The output.
   */
  public function getOutput(): ImageClassificationOutput {
    $item = new ImageClassificationItem('test', 0.5);
    return new ImageClassificationOutput([$item], [
      [
        'label' => 'test',
        'confidence' => 0.5,
      ],
    ], []);
  }

}
