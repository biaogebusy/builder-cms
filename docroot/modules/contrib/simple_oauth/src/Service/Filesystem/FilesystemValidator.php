<?php

namespace Drupal\simple_oauth\Service\Filesystem;

use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException;
use Drupal\simple_oauth\Service\Exception\FilesystemValidationException;

/**
 * The file system validator.
 *
 * @internal
 */
class FilesystemValidator implements FilesystemValidatorInterface {

  /**
   * The file system checker.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface
   */
  private FileSystemCheckerInterface $fileSystemChecker;

  /**
   * FilesystemValidator constructor.
   *
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface $file_system_checker
   *   The file system checker.
   */
  public function __construct(FileSystemCheckerInterface $file_system_checker) {
    $this->fileSystemChecker = $file_system_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOpensslExtensionExist(string $extension): void {
    if (!$this->fileSystemChecker->isExtensionEnabled($extension)) {
      throw new ExtensionNotLoadedException(
        strtr('Extension "@ext" is not enabled.', ['@ext' => $extension])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateAreDirs(array $paths): void {
    foreach ($paths as $path) {
      if (!$this->fileSystemChecker->isDirectory($path)) {
        throw new FilesystemValidationException(
          strtr('Directory "@path" is not a valid directory.', ['@path' => $path])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateAreWritable(array $paths): void {
    foreach ($paths as $path) {
      if (!$this->fileSystemChecker->isWritable($path)) {
        throw new FilesystemValidationException(
          strtr('Path "@path" is not writable.', ['@path' => $path])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateNotFilePublicPath(array $paths): void {
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
