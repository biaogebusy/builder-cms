<?php

namespace Drupal\simple_oauth\Repositories;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

/**
 * The repository for the Auth Code grant.
 */
class AuthCodeRepository implements AuthCodeRepositoryInterface {

  use RevocableTokenRepositoryTrait;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected static $bundleId = 'auth_code';

  /**
   * The OAuth2 entity class name.
   *
   * @var string
   */
  protected static $entityClass = 'Drupal\simple_oauth\Entities\AuthCodeEntity';

  /**
   * The OAuth2 entity interface name.
   *
   * @var string
   */
  protected static $entityInterface = 'League\OAuth2\Server\Entities\AuthCodeEntityInterface';

  /**
   * {@inheritdoc}
   */
  public function getNewAuthCode(): AuthCodeEntityInterface {
    return $this->getNew();
  }

  /**
   * {@inheritdoc}
   */
  public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void {
    $this->persistNew($authCodeEntity);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAuthCode($codeId): void {
    $this->revoke($codeId);
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthCodeRevoked($codeId): bool {
    return $this->isRevoked($codeId);
  }

}
