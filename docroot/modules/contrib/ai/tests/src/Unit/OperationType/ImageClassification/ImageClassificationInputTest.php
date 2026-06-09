<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageClassification;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput
 */
class ImageClassificationInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('bla.png', $input->getImageFile()->getFileName());
    $image = new ImageFile('bla', 'image/png', 'bla2.png');
    $input->setImageFile($image);
    $this->assertEquals('bla2.png', $input->getImageFile()->getFileName());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageClassification\ImageClassificationInput
   *   The input.
   */
  public function getInput(): ImageClassificationInput {
    $image = new ImageFile('bla', 'image/png', 'bla.png');
    return new ImageClassificationInput($image);
  }

}
