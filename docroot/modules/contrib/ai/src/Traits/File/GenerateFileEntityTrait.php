<?php

namespace Drupal\ai\Traits\File;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Trait to add the possibility to store files directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateFileEntityTrait {

  /**
   * Get as file entity.
   *
   * @param string $file_path
   *   A path to store the file at: for example public:://images/.
   * @param string $filename
   *   The filename.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   */
  public function getAsFileEntity(string $file_path = '', string $filename = ''): File {
    // Set defaults.
    if (!$file_path) {
      $file_path = 'public://';
    }
    if (!$filename) {
      if ($this->filename) {
        $filename = $this->filename;
      }
      else {
        $filename = uniqid();
      }
    }

    // Get the file system.
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    // Prepare the directory.
    $file_system->prepareDirectory($file_path, FileSystemInterface::CREATE_DIRECTORY);

    $file_storage = \Drupal::service('entity_type.manager')->getStorage('file');

    // The file url is the $file_path + the $filename, with correct dividers
    // between.
    $file_url = (str_ends_with($file_path, '//')) ? $file_path . $filename : rtrim($file_path, '/') . '/' . $filename;

    // Generate a file from string and rename if it already exists.
    $file_path = $file_system->saveData($this->getBinary(), $file_url);

    // Generate a file entity.
    $file = $file_storage->create([
      'uri' => $file_path,
      'status' => 1,
      'uid' => \Drupal::currentUser()->id(),
      'filename' => basename($file_path),
    ]);
    $file->save();

    return $file;
  }

}
