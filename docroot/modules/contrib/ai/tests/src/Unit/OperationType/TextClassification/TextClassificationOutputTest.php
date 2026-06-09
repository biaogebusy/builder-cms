<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextClassification;

use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use Drupal\ai\OperationType\TextClassification\TextClassificationOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextClassification\TextClassificationOutput
 */
class TextClassificationOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals('positive', $output->getNormalized()[0]->getLabel());
    $this->assertEquals(0.95, $output->getNormalized()[0]->getConfidenceScore());
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $output = $this->getOutput();
    $array = $output->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('normalized', $array);
    $this->assertArrayHasKey('rawOutput', $array);
    $this->assertArrayHasKey('metadata', $array);
  }

  /**
   * Helper function to get the output.
   *
   * @return \Drupal\ai\OperationType\TextClassification\TextClassificationOutput
   *   The output.
   */
  public function getOutput(): TextClassificationOutput {
    $item = new TextClassificationItem('positive', 0.95);
    return new TextClassificationOutput([$item], [
      [
        'label' => 'positive',
        'confidence' => 0.95,
      ],
    ], []);
  }

}
