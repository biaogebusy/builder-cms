<?php

namespace Drupal\simple_oauth\Drush\Commands;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException;
use Drupal\simple_oauth\Service\Exception\FilesystemValidationException;
use Drupal\simple_oauth\Service\KeyGeneratorService;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Simple OAuth.
 */
class SimpleOauthCommands extends DrushCommands {

  /**
   * The key generator.
   *
   * @var \Drupal\simple_oauth\Service\KeyGeneratorService
   */
  private $keygen;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * SimpleOauthCommands constructor.
   */
  public function __construct(KeyGeneratorService $keygen, FileSystemInterface $file_system) {
    $this->keygen = $keygen;
    $this->fileSystem = $file_system;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('simple_oauth.key.generator'),
      $container->get('file_system')
    );
  }

  /**
   * Checks whether the give uri is a directory, without throwing errors.
   *
   * @param string $uri
   *   The uri to check.
   *
   * @return bool
   *   TRUE if it's a directory. FALSE otherwise.
   */
  private function isDirectory($uri) {
    return @is_dir($uri);
  }

  /**
   * Generate Oauth2 Keys.
   *
   * @param string $keypath
   *   The full path were the key files will be saved.
   *
   * @usage simple-oauth:generate-keys /var/www/drupal-example.org/keys
   *   Creates the keys in the /var/www/drupal-example.org/keys directory.
   *
   * @command simple-oauth:generate-keys
   * @aliases so:generate-keys, sogk
   *
   * @validate-module-enabled simple_oauth
   */
  public function generateKeys($keypath) {
    if (!$this->isDirectory($keypath)) {
      $this->fileSystem->mkdir($keypath);
    }
    $keys_path = $this->fileSystem->realpath($keypath);

    try {
      $this->keygen->generateKeys($keys_path);
      $this->logger()->notice(
        'Keys successfully generated at {path}.',
        ['path' => $keypath]
      );
    }
    catch (FilesystemValidationException $e) {
      $this->logger()->error($e->getMessage());
    }
    catch (ExtensionNotLoadedException $e) {
      $this->logger()->error($e->getMessage());
    }
  }

}
