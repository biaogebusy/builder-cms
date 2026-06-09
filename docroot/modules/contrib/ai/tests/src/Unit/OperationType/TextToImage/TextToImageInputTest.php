<?php

namespace Drupal\Tests\ai\Unit\OperationType\TextToImage;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\TextToImage\TextToImageInput
 */
class TextToImageInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('Something', $input->getText());
    $input->setText('Something2');
    $this->assertEquals('Something2', $input->getText());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\TextToImage\TextToImageInput
   *   The input.
   */
  public function getInput(): TextToImageInput {
    return new TextToImageInput('Something');
  }

}
