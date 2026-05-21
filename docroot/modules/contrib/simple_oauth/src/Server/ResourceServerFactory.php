<?php

namespace Drupal\simple_oauth\Server;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer;

/**
 * The resource server factory.
 */
class ResourceServerFactory implements ResourceServerFactoryInterface {

  /**
   * The simple_oauth settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The access token repository.
   *
   * @var \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface
   */
  protected AccessTokenRepositoryInterface $accessTokenRepository;

  /**
   * Constructs ResourceServerFactory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface $access_token_repository
   *   The access token repository.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    AccessTokenRepositoryInterface $access_token_repository,
  ) {
    $this->config = $config_factory->get('simple_oauth.settings');
    $this->fileSystem = $file_system;
    $this->accessTokenRepository = $access_token_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function get(): ResourceServer {
    return new ResourceServer(
      $this->accessTokenRepository,
      $this->getPublicKey()
    );
  }

  /**
   * Get the public key.
   *
   * @return \League\OAuth2\Server\CryptKey
   *   Returns the public key as crypt key.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   If public key is not set.
   */
  protected function getPublicKey(): CryptKey {
    $public_key_path = $this->config->get('public_key');
    $file_path = $this->fileSystem->realpath($public_key_path) ?: $public_key_path;
    $key = file_get_contents($file_path);

    if (!$key) {
      throw OAuthServerException::serverError('You need to set the OAuth2 public key.');
    }

    return new CryptKey(
      $key,
      NULL,
      Settings::get('simple_oauth.key_permissions_check', TRUE)
    );
  }

}
