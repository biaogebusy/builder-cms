<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\ImageToImage;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;

/**
 * This tests the Image to Image calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\ImageToImage\ImageToImageInterface
 *
 * @group ai
 */
class ImageToImageInterfaceTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'image',
    'field',
    'system',
  ];

  /**
   * Setup the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', [
      'file_usage',
    ]);
  }

  /**
   * Test the text to image service with mockup EchoAI Provider.
   */
  public function testImageToImageNormalized(): void {
    // Mockup image file.
    $image_file = new ImageFile('funny-binary', 'image/png', 'public://image-1024x1024.png');

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ImageToImageInput($image_file);
    $image_files = $provider->imageToImage($input, 'upscale')->getNormalized();
    // Should be an array of ImageFile objects.
    $this->assertIsArray($image_files);
    // The array should have 1 element.
    $this->assertCount(1, $image_files);
    // The first object should be an ImageFile object.
    $this->assertInstanceOf(ImageFile::class, $image_files[0]);
    // The binary should be the same as the input.
    $this->assertSame('funny-binary', $image_files[0]->getBinary());
    // The filename should be the same as the input.
    $this->assertSame('public://image-1024x1024.png', $image_files[0]->getFilename());
    // The mime type should be the same as the input.
    $this->assertSame('image/png', $image_files[0]->getMimeType());
  }

}
