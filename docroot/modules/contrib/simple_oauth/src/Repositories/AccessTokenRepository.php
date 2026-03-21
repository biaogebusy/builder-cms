<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\simple_oauth\Entities\AccessTokenEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * The access token repository.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface {

  use RevocableTokenRepositoryTrait;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected static $bundleId = 'access_token';

  /**
   * The OAuth2 entity class name.
   *
   * @var string
   */
  protected static $entityClass = 'Drupal\simple_oauth\Entities\AccessTokenEntity';

  /**
   * The OAuth2 entity interface name.
   *
   * @var string
   */
  protected static $entityInterface = 'League\OAuth2\Server\Entities\AccessTokenEntityInterface';

  /**
   * {@inheritdoc}
   */
  public function persistNewAccessToken(AccessTokenEntityInterface $access_token_entity) {
    $this->persistNew($access_token_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken($token_id) {
    $this->revoke($token_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessTokenRevoked($token_id) {
    return $this->isRevoked($token_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getNewToken(ClientEntityInterface $client_entity, array $scopes, $user_identifier = NULL) {
    $access_token = new AccessTokenEntity();
    $access_token->setClient($client_entity);
    foreach ($scopes as $scope) {
      $access_token->addScope($scope);
    }
    $access_token->setUserIdentifier($user_identifier);

    return $access_token;
  }

}
