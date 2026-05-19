<?php

namespace Drupal\simple_oauth\Drush\Commands;

use Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException;
use Drupal\simple_oauth\Service\Exception\FilesystemValidationException;
use Drupal\simple_oauth\Service\KeyGeneratorServiceInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for Simple OAuth.
 */
class SimpleOauthCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'simple_oauth.key.generator')] protected KeyGeneratorServiceInterface $keygen,
  ) {
    parent::__construct();
    $this->keygen = $keygen;
  }

  /**
   * Generate Oauth2 Keys.
   */
  #[CLI\Argument(name: 'keypath', description: 'The full path or a file stream where the key files will be saved.')]
  #[CLI\Command(name: 'simple-oauth:generate-keys', aliases: ['so:generate-keys', 'sogk'])]
  #[CLI\Usage(name: 'simple-oauth:generate-keys /var/www/drupal-example.org/keys', description: 'Creates the keys in the /var/www/drupal-example.org/keys directory.')]
  public function generateKeys(string $keypath): void {
    try {
      $this->keygen->generateKeys($keypath);
      $this->logger()->notice(
        'Keys successfully generated at {path}.',
        ['path' => $keypath]
      );
    }
    catch (FilesystemValidationException | ExtensionNotLoadedException $e) {
      $this->logger()->error($e->getMessage());
    }
  }

}
