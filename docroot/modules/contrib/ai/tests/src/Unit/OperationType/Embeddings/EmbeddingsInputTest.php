<?php

namespace Drupal\Tests\ai\Unit\OperationType\Embeddings;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Embeddings\EmbeddingsInput
 */
class EmbeddingsInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('This is a text to embed', $input->getPrompt());
    $this->assertNull($input->getImage());
    $input->setPrompt('This is a new text to embed');
    $this->assertEquals('This is a new text to embed', $input->getPrompt());
    $image = new ImageFile('bla', 'image/png', 'bla.png');
    $input->setImage($image);
    $this->assertEquals($image, $input->getImage());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Embeddings\EmbeddingsInput
   *   The input.
   */
  public function getInput(): EmbeddingsInput {
    return new EmbeddingsInput('This is a text to embed');
  }

}
