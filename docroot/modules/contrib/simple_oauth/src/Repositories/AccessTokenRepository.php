<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Entities\AccessTokenEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The access token repository.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface {

  use RevocableTokenRepositoryTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SerializerInterface $serializer,
    protected ClassResolverInterface $classResolver,
  ) {
  }

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected static string $bundleId = 'access_token';

  /**
   * The OAuth2 entity class name.
   *
   * @var string
   */
  protected static string $entityClass = AccessTokenEntity::class;

  /**
   * The OAuth2 entity interface name.
   *
   * @var string
   */
  protected static string $entityInterface = AccessTokenEntityInterface::class;

  /**
   * {@inheritdoc}
   */
  public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void {
    $this->persistNew($accessTokenEntity);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken($tokenId): void {
    $this->revoke($tokenId);
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessTokenRevoked($tokenId): bool {
    return $this->isRevoked($tokenId);
  }

  /**
   * {@inheritdoc}
   */
  public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, string|null $userIdentifier = NULL): AccessTokenEntityInterface {
    $access_token = $this->classResolver->getInstanceFromDefinition($this::$entityClass);
    $access_token->setClient($clientEntity);
    foreach ($scopes as $scope) {
      $access_token->addScope($scope);
    }
    if (!is_null($userIdentifier)) {
      $access_token->setUserIdentifier($userIdentifier);
    }

    return $access_token;
  }

}
