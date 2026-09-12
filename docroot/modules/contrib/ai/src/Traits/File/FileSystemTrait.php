<?php

namespace Drupal\ai\Traits\File;

use Drupal\Core\File\FileSystemInterface;

/**
 * Trait to provide file system functionality to classes that need it.
 *
 * @package Drupal\ai\Traits\File
 */
trait FileSystemTrait {

  /**
   * {@inheritdoc}
   */
  public function getFileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

}
