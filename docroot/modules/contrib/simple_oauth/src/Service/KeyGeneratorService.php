<?php

namespace Drupal\simple_oauth\Service;

use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\File\FileSystemInterface;
use Drupal\simple_oauth\Service\Filesystem\FileSystemChecker;
use Drupal\simple_oauth\Service\Filesystem\FilesystemValidator;

/**
 * Generates the signature keys.
 *
 * @internal
 */
class KeyGeneratorService {

  /**
   * The filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * The filesystem checker.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker
   */
  private $fileSystemChecker;

  /**
   * The filesystem validator.
   *
   * @var \Drupal\simple_oauth\Service\Filesystem\FilesystemValidator
   */
  private $validator;

  /**
   * KeyGeneratorService constructor.
   *
   * @param \Drupal\simple_oauth\Service\Filesystem\FileSystemChecker $file_system_checker
   *   The file system checker.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemChecker $file_system_checker, FileSystemInterface $file_system) {
    $this->fileSystemChecker = $file_system_checker;
    $this->fileSystem = $file_system;
    $this->validator = new FilesystemValidator($file_system_checker);
  }

  /**
   * Generate both public key and private key on the given paths.
   *
   * If no public path is given, then the private path is going to be use for
   * both keys.
   *
   * @param string $dir_path
   *   Private key path.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function generateKeys($dir_path) {
    // Create path array.
    $key_names = ['private', 'public'];

    // Validate.
    $this->validator->validateOpensslExtensionExist('openssl');
    $this->validator->validateAreDirs([$dir_path]);
    $this->validator->validateAreWritable([$dir_path]);
    $this->validator->validateNotFilePublicPath([$dir_path]);

    FileSecurity::writeHtaccess($dir_path);
    FileSecurity::writeWebConfig($dir_path);

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
