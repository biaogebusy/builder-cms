<?php

namespace Drupal\simple_oauth\Server;

use Defuse\Crypto\Core;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;

/**
 * Authorization Server factory.
 */
class AuthorizationServerFactory implements AuthorizationServerFactoryInterface {

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
   * The grant plugin manager.
   *
   * @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface
   */
  protected Oauth2GrantManagerInterface $grantManager;

  /**
   * The client repository.
   *
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected ClientRepositoryInterface $clientRepository;

  /**
   * The scope repository.
   *
   * @var \League\OAuth2\Server\Repositories\ScopeRepositoryInterface
   */
  protected ScopeRepositoryInterface $scopeRepository;

  /**
   * The access token repository.
   *
   * @var \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface
   */
  protected AccessTokenRepositoryInterface $accessTokenRepository;

  /**
   * The refresh token repository.
   *
   * @var \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
   */
  protected RefreshTokenRepositoryInterface $refreshTokenRepository;

  /**
   * The response type.
   *
   * @var \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface|null
   */
  protected ?ResponseTypeInterface $responseType;

  /**
   * Constructs AuthorizationServerFactory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The grant manager.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository.
   * @param \League\OAuth2\Server\Repositories\ScopeRepositoryInterface $scope_repository
   *   The scope repository.
   * @param \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface $access_token_repository
   *   The access token repository.
   * @param \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface $refresh_token_repository
   *   The refresh token repository.
   * @param \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface|null $response_type
   *   The authorization server response type.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    Oauth2GrantManagerInterface $grant_manager,
    ClientRepositoryInterface $client_repository,
    ScopeRepositoryInterface $scope_repository,
    AccessTokenRepositoryInterface $access_token_repository,
    RefreshTokenRepositoryInterface $refresh_token_repository,
    ?ResponseTypeInterface $response_type,
  ) {
    $this->config = $config_factory->get('simple_oauth.settings');
    $this->fileSystem = $file_system;
    $this->grantManager = $grant_manager;
    $this->clientRepository = $client_repository;
    $this->scopeRepository = $scope_repository;
    $this->accessTokenRepository = $access_token_repository;
    $this->refreshTokenRepository = $refresh_token_repository;
    $this->responseType = $response_type;
  }

  /**
   * {@inheritdoc}
   */
  public function get(Consumer $client): AuthorizationServer {
    $server = new AuthorizationServer(
      $this->clientRepository,
      $this->accessTokenRepository,
      $this->scopeRepository,
      $this->getPrivateKey(),
      Core::ourSubstr($this->getSalt(), 0, 32),
      $this->responseType
    );

    $expiration = new \DateInterval(sprintf('PT%dS', $client->get('access_token_expiration')->value));
    foreach ($client->get('grant_types')->getValue() as $item) {
      $grant_type = $this->getGrantType($item['value'], $client);
      $server->enableGrantType($grant_type, $expiration);
    }

    return $server;
  }

  /**
   * Get the private key.
   *
   * @return \League\OAuth2\Server\CryptKey
   *   Returns the private key as crypt key.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   If private key is not set.
   */
  protected function getPrivateKey(): CryptKey {
    $private_key_path = $this->config->get('private_key');
    $file_path = $this->fileSystem->realpath($private_key_path) ?: $private_key_path;

    $key = file_get_contents($file_path);

    if (!$key) {
      throw OAuthServerException::serverError('You need to set the OAuth2 private key.');
    }

    return new CryptKey(
      $key,
      NULL,
      Settings::get('simple_oauth.key_permissions_check', TRUE)
    );
  }

  /**
   * Get the settings hash salt.
   *
   * @return string
   *   Returns the hash salt.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   If hash salt is not long enough.
   * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
   */
  protected function getSalt(): string {
    $salt = Settings::getHashSalt();
    // The hash salt must be at least 32 characters long.
    if (Core::ourStrlen($salt) < 32) {
      throw OAuthServerException::serverError('Hash salt must be at least 32 characters long.');
    }

    return $salt;
  }

  /**
   * Get the grant type object.
   *
   * @param string $grant_type
   *   The grant type plugin id.
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The consumer entity.
   *
   * @return \League\OAuth2\Server\Grant\GrantTypeInterface
   *   Returns the grant type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   */
  protected function getGrantType(string $grant_type, Consumer $client): GrantTypeInterface {
    try {
      $plugin = $this->grantManager->createInstance($grant_type);
    }
    catch (PluginNotFoundException $exception) {
      throw OAuthServerException::invalidGrant('Grant type is not installed.');
    }

    return $plugin->getGrantType($client);
  }

}
