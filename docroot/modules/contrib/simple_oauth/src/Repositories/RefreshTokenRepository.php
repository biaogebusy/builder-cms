<?php

namespace Drupal\simple_oauth\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * The refresh token repository.
 */
class RefreshTokenRepository implements RefreshTokenRepositoryInterface {

  use RevocableTokenRepositoryTrait;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected static $bundleId = 'refresh_token';

  /**
   * The OAuth2 entity class name.
   *
   * @var string
   */
  protected static $entityClass = 'Drupal\simple_oauth\Entities\RefreshTokenEntity';

  /**
   * The OAuth2 entity interface name.
   *
   * @var string
   */
  protected static $entityInterface = 'League\OAuth2\Server\Entities\RefreshTokenEntityInterface';

  /**
   * {@inheritdoc}
   */
  public function getNewRefreshToken() {
    return $this->getNew();
  }

  /**
   * {@inheritdoc}
   */
  public function persistNewRefreshToken(RefreshTokenEntityInterface $refresh_token_entity) {
    $this->persistNew($refresh_token_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeRefreshToken($token_id) {
    $this->revoke($token_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isRefreshTokenRevoked($token_id) {
    return $this->isRevoked($token_id);
  }

}
