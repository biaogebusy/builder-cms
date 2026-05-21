<?php

namespace Drupal\simple_oauth\Plugin\Oauth2Grant;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Plugin\Oauth2GrantBase;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The refresh token grant plugin.
 *
 * @Oauth2Grant(
 *   id = "refresh_token",
 *   label = @Translation("Refresh Token")
 * )
 */
class RefreshToken extends Oauth2GrantBase implements ContainerFactoryPluginInterface {

  /**
   * The refresh token repository.
   *
   * @var \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
   */
  protected RefreshTokenRepositoryInterface $refreshTokenRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RefreshTokenRepositoryInterface $refresh_token_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('simple_oauth.repositories.refresh_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantType(Consumer $client): GrantTypeInterface {
    $refresh_token_repository = $this->refreshTokenRepository;
    $grant_type = new RefreshTokenGrant($refresh_token_repository);
    $refresh_token_ttl = !$client->get('refresh_token_expiration')->isEmpty ? $client->get('refresh_token_expiration')->value : 1209600;
    $duration = new \DateInterval(sprintf('PT%dS', $refresh_token_ttl));
    $grant_type->setRefreshTokenTTL($duration);
    return $grant_type;
  }

}
