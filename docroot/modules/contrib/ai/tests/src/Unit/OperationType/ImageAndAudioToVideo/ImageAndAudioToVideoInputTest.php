<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageAndAudioToVideo;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoInput
 */
class ImageAndAudioToVideoInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('bla.png', $input->getImageFile()->getFileName());
    $this->assertEquals('test.mp3', $input->getAudioFile()->getFileName());
    $image = new ImageFile('bla', 'image/png', 'bla2.png');
    $input->setImageFile($image);
    $this->assertEquals('bla2.png', $input->getImageFile()->getFileName());
    $audio = new AudioFile('bla', 'audio/mpeg', 'test2.mp3');
    $input->setAudioFile($audio);
    $this->assertEquals('test2.mp3', $input->getAudioFile()->getFileName());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoInput
   *   The input.
   */
  public function getInput(): ImageAndAudioToVideoInput {
    $image = new ImageFile('bla', 'image/png', 'bla.png');
    $audio = new AudioFile('bla', 'audio/mpeg', 'test.mp3');
    return new ImageAndAudioToVideoInput($image, $audio);
  }

}
