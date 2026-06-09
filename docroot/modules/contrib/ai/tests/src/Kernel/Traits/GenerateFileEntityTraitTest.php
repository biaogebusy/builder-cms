<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Traits;

use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\GenericType\AbstractFileBase;
use Drupal\file\Entity\File;

/**
 * Tests for the GenerateFileEntityTrait class.
 *
 * @group ai
 */
final class GenerateFileEntityTraitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file'];

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
    // Install user and file modules as it is required by the trait under test.
    $this->container->get('module_installer')->install(['user', 'file']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');

  }

  /**
   * Test getAsFileEntity with valid file path and filename.
   */
  public function testGetAsFileEntityWithValidPathAndFilename(): void {

    $this->handleFileEntityAssertions('public://');

  }

  /**
   * Test getAsFileEntity with empty file path.
   */
  public function testGetAsFileEntityWithEmptyPath(): void {

    $this->handleFileEntityAssertions('');

  }

  /**
   * Test getAsFileEntity with non-existing directory.
   */
  public function testGetAsFileEntityWithNonExistingDirectory(): void {

    $this->handleFileEntityAssertions('public://nonexistent/');

  }

  /**
   * Test getAsFileEntity with existing file.
   */
  public function testGetAsFileEntityWithExistingFile(): void {
    $expected_uri = 'public://file_0.ext';
    $original_uri = 'public://file.ext';
    $binary_data = 'test binary data';
    $mime_type = 'application/octet-stream';

    $trait = new class($binary_data, $mime_type) extends AbstractFileBase {};

    $trait->getAsFileEntity('public://', 'file.ext');
    $result = $trait->getAsFileEntity('public://', 'file.ext');

    $this->assertInstanceOf(File::class, $result);
    $this->assertEquals($expected_uri, $result->getFileUri());
    $this->assertEquals($mime_type, $result->getMimeType());
    $this->assertEquals('file_0.ext', $result->getFileName());

    // Delete the test files from filesystem.
    $this->fileSystem->delete($expected_uri);
    $this->fileSystem->delete($original_uri);
  }

  /**
   * Handles the file entity assertions for reusability.
   */
  private function handleFileEntityAssertions(string $path): void {
    $expected_uri = $path . 'file.ext';

    if ($path === '') {
      $expected_uri = 'public://file.ext';
    }

    $binary_data = 'test binary data';
    $mime_type = 'application/octet-stream';

    $trait = new class($binary_data, $mime_type) extends AbstractFileBase {};

    $result = $trait->getAsFileEntity($path, 'file.ext');
    $this->assertInstanceOf(File::class, $result);
    $this->assertEquals($expected_uri, $result->getFileUri());
    $this->assertEquals($mime_type, $result->getMimeType());
    $this->assertEquals('file.ext', $result->getFileName());

    // Delete the test file from filesystem.
    $this->fileSystem->delete($expected_uri);
  }

}
