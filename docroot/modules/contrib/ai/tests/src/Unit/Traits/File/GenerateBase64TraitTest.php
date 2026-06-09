<?php

namespace Drupal\Tests\ai\Unit\Traits\File;

use Drupal\ai\OperationType\GenericType\ImageFile;
use PHPUnit\Framework\TestCase;

/**
 * Tests to generate base64 encoded string trait.
 *
 * @group ai
 * @covers \Drupal\ai\Traits\File\GenerateBase64Trait
 */
class GenerateBase64TraitTest extends TestCase {

  /**
   * Tests the getAsBase64EncodedString method without data URL scheme.
   */
  public function testGetAsBase64EncodedStringWithoutDataUrlScheme() {
    $binaryData = 'test data';
    $base64Data = base64_encode($binaryData);
    $mimeType = 'text/plain';

    $mock = $this->getMockBuilder(ImageFile::class)
      ->onlyMethods(['getBinary', 'getMimeType'])
      ->getMock();

    // Mock the getBinary method.
    $mock->expects($this->once())
      ->method('getBinary')
      ->willReturn($binaryData);

    // Mock the getMimeType method.
    $mock->expects($this->exactly(2))
      ->method('getMimeType')
      ->willReturn($mimeType);

    $expected = 'data:' . $mimeType . ';base64,' . $base64Data;
    $this->assertEquals($expected, $mock->getAsBase64EncodedString());
  }

  /**
   * Tests the getAsBase64EncodedString method with data URL scheme.
   */
  public function testGetAsBase64EncodedStringWithDataUrlScheme() {
    $binaryData = 'test data';
    $base64Data = base64_encode($binaryData);
    $dataUrlScheme = 'data:image/png;base64,';

    $mock = $this->getMockBuilder(ImageFile::class)
      ->onlyMethods(['getBinary', 'getMimeType'])
      ->getMock();

    // Mock the getBinary method.
    $mock->expects($this->once())
      ->method('getBinary')
      ->willReturn($binaryData);

    $expected = $dataUrlScheme . $base64Data;
    $this->assertEquals($expected, $mock->getAsBase64EncodedString($dataUrlScheme));
  }

  /**
   * Tests the getAsBase64EncodedString method with null data URL scheme.
   */
  public function testGetAsBase64EncodedStringWithNullDataUrlSchemeAndNoMimeType() {
    $binaryData = 'test data';
    $base64Data = base64_encode($binaryData);

    $mock = $this->getMockBuilder(ImageFile::class)
      ->onlyMethods(['getBinary', 'getMimeType'])
      ->getMock();

    // Mock the getBinary method.
    $mock->expects($this->once())
      ->method('getBinary')
      ->willReturn($binaryData);

    // Mock the getMimeType method.
    $mock->expects($this->exactly(2))
      ->method('getMimeType')
      ->willReturn('text/plain');

    $expected = 'data:text/plain;base64,' . $base64Data;
    $this->assertEquals($expected, $mock->getAsBase64EncodedString());
  }

}
