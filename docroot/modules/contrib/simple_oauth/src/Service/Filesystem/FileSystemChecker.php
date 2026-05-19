<?php

namespace Drupal\simple_oauth\Service\Filesystem;

/**
 * The file system checker.
 *
 * @internal
 */
class FileSystemChecker implements FileSystemCheckerInterface {

  /**
   * {@inheritdoc}
   */
  public function isExtensionEnabled(string $extension): bool {
    return @extension_loaded($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function isDirectory(string $file_path): bool {
    return @is_dir($file_path);
  }

  /**
   * {@inheritdoc}
   */
  public function isWritable(string $file_path): bool {
    return @is_writable($file_path);
  }

  /**
   * {@inheritdoc}
   */
  public function fileExist(string $file_path): bool {
    return @file_exists($file_path);
  }

  /**
   * {@inheritdoc}
   */
  public function write(string $file_path, mixed $data): mixed {
    return @file_put_contents($file_path, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function isReadable(string $file_path): bool {
    return @is_readable($file_path);
  }

}
