<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Traits;

use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\ai\OperationType\GenericType\AbstractFileBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;

/**
 * Tests for the GenerateMediaEntityTrait class.
 *
 * @group ai
 */
final class GenerateMediaEntityTraitTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'media_test_source',
    'ai',
  ];

  /**
   * The test media type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected MediaType $testMediaType;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->container->get('file_system');

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', 'file_usage');
    $this->installConfig([
      'field',
      'system',
      'image',
      'file',
      'media',
    ]);

    // Create a test media type.
    // @see Drupal\media_test_source\Plugin\media\Source\TestDifferentDisplays
    $this->testMediaType = $this->createMediaType('test_different_displays');
  }

  /**
   * Test getAsMediaEntity with valid media type and file path.
   */
  public function testGetAsMediaEntityWithValidMediaTypeAndPath(): void {
    $expected_uri = 'public://file.ext';
    $binary_data = 'test binary data';
    $mime_type = 'application/octet-stream';
    // Source field name is reused from media_test_source submodule
    // "test_different_displays" plugin thus may be confusing.
    $source_field = $this->testMediaType
      ->getSource()
      ->getConfiguration()['source_field'] ?? 'field_media_different_display';

    $trait = new class($binary_data, $mime_type) extends AbstractFileBase {};

    $result = $trait->getAsMediaEntity(
      $this->testMediaType->get('id'),
      'public://',
      'file.ext'
    );

    $this->assertInstanceOf(Media::class, $result);

    $file = File::load($result->get($source_field)
      ->getValue()[0]['target_id'] ?? []
    );

    $this->assertEquals($expected_uri, $file?->getFileUri());
    $this->assertEquals($mime_type, $file?->getMimeType());
    $this->assertEquals('file.ext', $file?->getFileName());

  }

  /**
   * Test getMediaFilePath method.
   */
  public function testGetMediaFilePath(): void {
    $binary_data = 'test binary data';
    $mime_type = 'application/octet-stream';

    $trait = new class($binary_data, $mime_type) extends AbstractFileBase {};

    // Testing a private method use Reflection API.
    $method = new \ReflectionMethod($trait, 'getBaseMediaFieldDefinition');
    $method->setAccessible(TRUE);
    $base_field_definition = $method->invoke($trait, $this->testMediaType
      ->get('id')
    );
    // Set the uri scheme and directory settings manually.
    // @toto is there a way to do this from config field schema in setUp method?
    $base_field_definition->setSettings([
      'uri_scheme' => 'public',
      'file_directory' => '',
    ]);
    $method = new \ReflectionMethod($trait, 'getMediaFilePath');
    $method->setAccessible(TRUE);
    $file_path = $method->invoke($trait, $base_field_definition, 'file.ext');

    $this->assertNotEmpty($file_path);
    $this->assertEquals('public://', $file_path);
  }

  /**
   * Test getBaseMediaField method.
   */
  public function testGetBaseMediaField(): void {
    $binary_data = 'test binary data';
    $mime_type = 'application/octet-stream';

    $trait = new class($binary_data, $mime_type) extends AbstractFileBase {};

    // Testing a private method use Reflection API.
    $method = new \ReflectionMethod($trait, 'getBaseMediaField');
    $method->setAccessible(TRUE);
    $base_media_field = $method->invoke($trait, $this->testMediaType
      ->get('id')
    );

    $this->assertEquals($this->testMediaType
      ->getSource()
      ->getConfiguration()['source_field'] ?? 'field_media_different_display',
      $base_media_field
    );
  }

}
