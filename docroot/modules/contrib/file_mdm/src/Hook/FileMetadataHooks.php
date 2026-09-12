<?php

declare(strict_types=1);

namespace Drupal\file_mdm\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;
use Drupal\file_mdm\FileMetadataManagerInterface;

/**
 * Hook implementations for file_mdm.
 */
class FileMetadataHooks {

  /**
   * Implements hook_file_delete().
   */
  #[Hook('file_delete')]
  public function fileDelete(EntityInterface $entity): void {
    // Deletes any cached file metadata information upon deletion of a file
    // entity.
    assert($entity instanceof FileInterface);
    $fmdm = \Drupal::service(FileMetadataManagerInterface::class);
    $fmdm->deleteCachedMetadata($entity->getFileUri());
    $fmdm->release($entity->getFileUri());
  }

}
