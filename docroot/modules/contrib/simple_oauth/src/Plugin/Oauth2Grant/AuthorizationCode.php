<?php

namespace Drupal\simple_oauth\Plugin\Oauth2Grant;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Plugin\Oauth2GrantBase;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The authorization code grant plugin.
 *
 * @Oauth2Grant(
 *   id = "authorization_code",
 *   label = @Translation("Authorization Code"),
 * )
 */
class AuthorizationCode extends Oauth2GrantBase implements ContainerFactoryPluginInterface {

  /**
   * The authorization code repository.
   *
   * @var \League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface
   */
  protected AuthCodeRepositoryInterface $authCodeRepository;

  /**
   * The refresh token repository.
   *
   * @var \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
   */
  protected RefreshTokenRepositoryInterface $refreshTokenRepository;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AuthCodeRepositoryInterface $auth_code_repository, RefreshTokenRepositoryInterface $refresh_token_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->authCodeRepository = $auth_code_repository;
    $this->refreshTokenRepository = $refresh_token_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_oauth.repositories.auth_code'),
      $container->get('simple_oauth.repositories.refresh_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantType(Consumer $client): GrantTypeInterface {
    $auth_code_ttl = new \DateInterval(
      sprintf('PT%dS', $client->get('access_token_expiration')->value)
    );

    $refresh_token_enabled = $this->isRefreshTokenEnabled($client);

    /** @var \Drupal\simple_oauth\Repositories\OptionalRefreshTokenRepositoryInterface $refresh_token_repository */
    $refresh_token_repository = $this->refreshTokenRepository;
    if (!$refresh_token_enabled) {
      $refresh_token_repository->disableRefreshToken();
    }

    $grant_type = new AuthCodeGrant(
      $this->authCodeRepository,
      $refresh_token_repository,
      $auth_code_ttl
    );

    if ($refresh_token_enabled) {
      $refresh_token = !$client->get('refresh_token_expiration')->isEmpty ? $client->get('refresh_token_expiration')->value : 1209600;
      $refresh_token_ttl = new \DateInterval(
        sprintf('PT%dS', $refresh_token)
      );
      $grant_type->setRefreshTokenTTL($refresh_token_ttl);
    }

    // Make PKCE optional.
    $pkce_enabled = $client->get('pkce')->value;
    if (!$pkce_enabled) {
      $grant_type->disableRequireCodeChallengeForPublicClients();
    }

    return $grant_type;
  }

  /**
   * Checks if refresh token is enabled on the client.
   *
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The consumer entity.
   *
   * @return bool
   *   Returns boolean.
   */
  protected function isRefreshTokenEnabled(Consumer $client): bool {
    foreach ($client->get('grant_types')->getValue() as $grant_type) {
      if ($grant_type['value'] === 'refresh_token') {
        return TRUE;
      }
    }

    return FALSE;
  }

}
