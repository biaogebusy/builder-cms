<?php

namespace Drupal\simple_oauth\Service;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface;
use Drupal\simple_oauth\Service\Filesystem\FilesystemValidator;
use Drupal\simple_oauth\Service\Filesystem\FilesystemValidatorInterface;

/**
 * The default implementation of the key generator service.
 *
 * @internal
 */
class KeyGeneratorService implements KeyGeneratorServiceInterface {

  /**
   * The filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

  /**
   * The filesystem checker.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface
   */
  private FileSystemCheckerInterface $fileSystemChecker;

  /**
   * The filesystem validator.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FilesystemValidatorInterface
   */
  private FilesystemValidatorInterface $validator;

  /**
   * KeyGeneratorService constructor.
   *
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemCheckerInterface $file_system_checker
   *   The file system checker.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemCheckerInterface $file_system_checker, FileSystemInterface $file_system) {
    $this->fileSystemChecker = $file_system_checker;
    $this->fileSystem = $file_system;
    $this->validator = new FilesystemValidator($file_system_checker);
  }

  /**
   * {@inheritdoc}
   */
  public function generateKeys(string $dir_path): void {
    // Create path array.
    $key_names = ['private', 'public'];

    // Validate.
    $this->validator->validateOpensslExtensionExist('openssl');
    $this->validator->validateAreDirs([$dir_path]);
    $this->validator->validateAreWritable([$dir_path]);
    $this->validator->validateNotFilePublicPath([$dir_path]);

    FileSecurity::writeHtaccess($dir_path);
    // As writeWebConfig method is deprecated and removed from D11.
    // Also drupal dropped IIS support which require web.config so its ideal
    // to have a check and handle the backward compatibility.
    if (method_exists(FileSecurity::class, 'writeWebConfig')) {
      FileSecurity::writeWebConfig($dir_path);
    }

    // Create Keys array.
    $keys = KeyGenerator::generateKeys();

    // Create both keys.
    foreach ($key_names as $name) {
      // Key uri.
      $key_uri = "$dir_path/$name.key";
      // Remove old key, if existing.
      if (file_exists($key_uri)) {
        $this->fileSystem->unlink($key_uri);
      }
      // Write key content to key file.
      $this->fileSystemChecker->write($key_uri, $keys[$name]);
      // Set correct permission to key file.
      $this->fileSystem->chmod($key_uri, 0600);
    }
  }

}
