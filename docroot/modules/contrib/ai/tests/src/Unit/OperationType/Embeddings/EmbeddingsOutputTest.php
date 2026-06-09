<?php

namespace Drupal\Tests\ai\Unit\OperationType\Embeddings;

use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Embeddings\EmbeddingsInput
 */
class EmbeddingsOutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    $this->assertEquals([12, 13], $output->getNormalized());
    $this->assertEquals([
      'vectors' => [
        12, 13,
      ],
    ], $output->getRawOutput());
    $this->assertEquals([], $output->getMetadata());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Embeddings\EmbeddingsInput
   *   The output.
   */
  public function getOutput(): EmbeddingsOutput {
    return new EmbeddingsOutput([12, 13], [
      'vectors' => [
        12, 13,
      ],
    ], []);
  }

}
