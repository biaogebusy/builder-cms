<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageToImage;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageToImage\ImageToImageInput
 */
class ImageToImageInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('bla.png', $input->getImageFile()->getFileName());
    $this->assertEquals('image/png', $input->getImageFile()->getMimeType());
    $this->assertEquals('bla', $input->getImageFile()->getBinary());

    $this->assertEquals('mask.png', $input->getMask()->getFileName());
    $this->assertEquals('image/png', $input->getMask()->getMimeType());
    $this->assertEquals('mask', $input->getMask()->getBinary());

    $this->assertEquals('This is a test prompt', $input->getPrompt());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageToImage\ImageToImageInput
   *   The input.
   */
  public function getInput(): ImageToImageInput {
    $image = new ImageFile('bla', 'image/png', 'bla.png');
    $input = new ImageToImageInput($image);
    $input->setPrompt('This is a test prompt');
    $mask = new ImageFile('mask', 'image/png', 'mask.png');
    $input->setMask($mask);
    return $input;
  }

}
