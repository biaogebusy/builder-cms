<?php

namespace Drupal\Tests\file_mdm\Kernel;

use Drupal\file_mdm\FileMetadataInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base test class for File Metadata Manager.
 */
abstract class FileMetadataManagerTestBase extends KernelTestBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->fileSystem = \Drupal::service('file_system');
    $this->moduleList = \Drupal::service('extension.list.module');
    $this->installConfig(['file_mdm']);
  }

  /**
   * Returns the count of metadata keys found in the file.
   *
   * @param \Drupal\file_mdm\FileMetadataInterface $file_md
   *   The FileMetadata object.
   * @param string $metadata_id
   *   The file metadata plugin id.
   * @param mixed $options
   *   (optional) Allows specifying additional options to control the list of
   *   metadata keys returned.
   *
   * @return int
   *   The count of metadata keys found in the file.
   */
  protected function countMetadataKeys(FileMetadataInterface $file_md, $metadata_id, $options = NULL) {
    $supported_keys = $file_md->getSupportedKeys($metadata_id, $options);
    $count = 0;
    foreach ($supported_keys as $key) {
      if ($file_md->getMetadata($metadata_id, $key)) {
        $count++;
      }
    }
    return $count;
  }

}
