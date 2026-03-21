<?php

namespace Drupal\simple_oauth\Service\Filesystem;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\simple_oauth\Service\Exception\FilesystemValidationException;
use Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException;

/**
 * @internal
 */
class FilesystemValidator {

  /**
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker
   */
  private $fileSystemChecker;

  /**
   * FilesystemValidator constructor.
   *
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker $file_system_checker
   */
  public function __construct(FileSystemChecker $file_system_checker) {
    $this->fileSystemChecker = $file_system_checker;
  }

  /**
   * Validate {@var $ext_name} extension exist.
   *
   * @param string $ext_name
   *   extension name.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException
   */
  public function validateOpensslExtensionExist($ext_name) {
    if (!$this->fileSystemChecker->isExtensionEnabled($ext_name)) {
      throw new ExtensionNotLoadedException(
        strtr('Extension "@ext" is not enabled.', ['@ext' => $ext_name])
      );
    }
  }

  /**
   * Validate that {@var $paths} are directories.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateAreDirs($paths) {
    foreach ($paths as $path) {
      if (!$this->fileSystemChecker->isDirectory($path)) {
        throw new FilesystemValidationException(
          strtr('Directory "@path" is not a valid directory.', ['@path' => $path])
        );
      }
    }
  }

  /**
   * Validate that {@var $paths} are writable.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateAreWritable($paths) {
    foreach ($paths as $path) {
      if (!$this->fileSystemChecker->isWritable($path)) {
        throw new FilesystemValidationException(
          strtr('Path "@path" is not writable.', ['@path' => $path])
        );
      }
    }
  }

  /**
   * Validate that {@var $paths} are not the file public path.
   *
   * @param array $paths
   *   List of URIs.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function validateNotFilePublicPath($paths) {
    $file_public_path = PublicStream::basePath();
    foreach ($paths as $path) {
      if ($file_public_path === $path) {
        throw new FilesystemValidationException(
          strtr('Path "@path" cannot be the file public path.', ['@path' => $path])
        );
      }
    }
  }

}
