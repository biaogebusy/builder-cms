<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextClassification;

use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextClassification\TextClassificationInput
 */
class TextClassificationInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('This is a test sentence.', $input->getText());
    $input->setText('Another sentence.');
    $this->assertEquals('Another sentence.', $input->getText());
  }

  /**
   * Test getting and setting labels.
   */
  public function testLabels(): void {
    $input = $this->getInput();
    $this->assertEquals([], $input->getLabels());
    $input->setLabels(['positive', 'negative']);
    $this->assertEquals(['positive', 'negative'], $input->getLabels());
  }

  /**
   * Test the toString method.
   */
  public function testToString(): void {
    $input = $this->getInput();
    $this->assertEquals('This is a test sentence.', $input->toString());
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $input = $this->getInput();
    $array = $input->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('text', $array);
    $this->assertArrayHasKey('labels', $array);
    $this->assertEquals('This is a test sentence.', $array['text']);
    $this->assertEquals([], $array['labels']);
  }

  /**
   * Helper function to get the input.
   *
   * @return \Drupal\ai\OperationType\TextClassification\TextClassificationInput
   *   The input.
   */
  public function getInput(): TextClassificationInput {
    return new TextClassificationInput('This is a test sentence.');
  }

}
