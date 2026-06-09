<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\TextToImage;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\OperationType\TextToImage\TextToImageOutput;
use Drupal\file\Entity\File;

/**
 * This tests the Text to Image calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\TextToImage\TextToImageInterface
 *
 * @group ai
 */
class TextToImageInterfaceTest extends KernelTestBase {

  use MediaTypeCreationTrait;

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
    'media',
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
    $this->installEntitySchema('media');
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installSchema('file', [
      'file_usage',
    ]);
    // Install configs.
    $this->installConfig(['media', 'file', 'image']);

    $this->createMediaType('image', ['id' => 'image']);
  }

  /**
   * Test the text to image service with mockup EchoAI Provider.
   */
  public function testTextToImageNormalized(): void {
    $text = 'A cow';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new TextToImageInput($text);
    $provider->setConfiguration([
      'response_format' => 'url',
      'n' => 2,
      'size' => '1024x1024',
    ]);
    $image_file = $provider->textToImage($input, 'dall-e-2');
    // Should be a TextToImageOutput object.
    $this->assertInstanceOf(TextToImageOutput::class, $image_file);

    $normalized = $image_file->getNormalized();
    // Normalized output should be an array.
    $this->assertIsArray($normalized);
    // The array should have 2 elements.
    $this->assertCount(2, $normalized);
    // The first object should be an ImageFile object.
    $this->assertInstanceOf(ImageFile::class, $normalized[0]);

    // It should be possible to get as a binary.
    $binary = $normalized[0]->getAsBinary();
    $file_binary = file_get_contents(\Drupal::service('module_handler')->getModule('ai_test')->getPath() . '/assets/image-1024x1024.png');
    $this->assertIsString($binary);
    $this->assertSame($file_binary, $binary);

    // It should be possible to get as a base64.
    $base64 = $normalized[0]->getAsBase64EncodedString();
    $file_base64 = 'data:image/png;base64,' . base64_encode($file_binary);
    $this->assertIsString($base64);
    $this->assertSame($file_base64, $base64);

    // It should be possible to save as a file.
    $random_file_name = uniqid() . '.png';
    $file = $normalized[0]->getAsFileEntity('public://', $random_file_name);
    $this->assertInstanceOf(File::class, $file);
    // Read this file and double check so we got the right file resolution.
    $file_binary = file_get_contents($file->getFileUri());
    $this->assertSame($file_binary, $binary);
    $resolution = getimagesize($file->getFileUri());
    $this->assertSame([1024, 1024], [$resolution[0], $resolution[1]]);
    // Remove the actual file on disk, since testing framework doesn't.
    unlink($file->getFileUri());

    // It should be possible to save as a media entity.
    $random_file_name = uniqid() . '.png';
    $media = $normalized[0]->getAsMediaEntity('image', 'public://', $random_file_name);
    $this->assertSame('image', $media->bundle());
    $this->assertSame($random_file_name, $media->get('name')->value);
    $this->assertSame('public://' . $random_file_name, $media->get('field_media_image')->entity->getFileUri());
    unlink($media->get('field_media_image')->entity->getFileUri());
  }

  /**
   * Test the text to image service with dall-e-3.
   */
  public function testTextToImageNormalizedOtherModel(): void {
    $text = 'A cow';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new TextToImageInput($text);
    $provider->setConfiguration([
      'response_format' => 'url',
      'size' => '1024x1024',
    ]);
    $image_file = $provider->textToImage($input, 'dall-e-3');
    // Should be a TextToImageOutput object.
    $this->assertInstanceOf(TextToImageOutput::class, $image_file);
    $normalized = $image_file->getNormalized();
    // Normalized output should be an array.
    $this->assertIsArray($normalized);
    // The array should have 2 elements.
    $this->assertCount(2, $normalized);
  }

  /**
   * Test the text to image service with raw input to response.
   */
  public function testTextToImageRaw(): void {
    $text = 'A cow';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $provider->setConfiguration([
      'response_format' => 'url',
      'size' => '1024x1024',
    ]);
    $image_file = $provider->textToImage($text, 'dall-e-3');
    // Should be a TextToImageOutput object.
    $this->assertInstanceOf(TextToImageOutput::class, $image_file);
    $raw = $image_file->getRawOutput();
    // Raw output should be a string.
    $this->assertIsArray($raw);
  }

}
