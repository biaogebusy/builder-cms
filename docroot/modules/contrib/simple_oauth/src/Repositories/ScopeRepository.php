<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Entities\ScopeEntity;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * The repository for scopes.
 */
class ScopeRepository implements ScopeRepositoryInterface {

  protected const SCOPE_FIELD = [
    'client_credentials' => 'scopes',
    'authorization_code' => 'authorization_code_scopes',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The scope provider.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface
   */
  protected Oauth2ScopeProviderInterface $scopeProvider;

  /**
   * ScopeRepository constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_oauth\Oauth2ScopeProviderInterface $scope_provider
   *   The scope provider.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Oauth2ScopeProviderInterface $scope_provider) {
    $this->entityTypeManager = $entity_type_manager;
    $this->scopeProvider = $scope_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface {
    $scope = $this->scopeProvider->loadByName($identifier);
    return $scope ? $this->scopeFactory($scope) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = NULL, ?string $authCodeId = NULL): array {
    $default_user = NULL;
    if (!$clientEntity->getDrupalEntity()->get('user_id')->isEmpty()) {
      $default_user = $clientEntity->getDrupalEntity()->get('user_id')->entity;
    }

    $user = $userIdentifier ? $this->entityTypeManager->getStorage('user')->load($userIdentifier) : $default_user;
    if (!$user && $grantType !== 'refresh_token') {
      return [];
    }

    $default_scopes = [];
    $allowed_scopes = [];
    $client_drupal_entity = $clientEntity->getDrupalEntity();
    // Only process default scopes if the grant type has a mapped field.
    if ($grantType !== 'refresh_token' && isset(static::SCOPE_FIELD[$grantType])) {
      $scope_field = static::SCOPE_FIELD[$grantType];
      if (!$client_drupal_entity->get($scope_field)->isEmpty()) {
        foreach ($client_drupal_entity->get($scope_field)->getScopes() as $scope) {
          $default_scope = $this->scopeFactory($scope);
          $default_scopes[] = $default_scope;
          $allowed_scopes[] = $default_scope->getIdentifier();
        }

        // Limit the scopes if default scopes are set on the consumer for the
        // client credentials grant type.
        if ($grantType === 'client_credentials') {
          foreach ($scopes as $scope) {
            if (!in_array($scope->getIdentifier(), $allowed_scopes)) {
              throw OAuthServerException::invalidScope($scope->getIdentifier());
            }
          }
        }
      }
    }

    $finalized_scopes = !empty($scopes) ? $scopes : $default_scopes;

    // Validate scopes if the associated grant type is enabled.
    foreach ($finalized_scopes as $finalized_scope) {
      if ($finalized_scope instanceof ScopeEntity && !$finalized_scope->getScopeObject()->isGrantTypeEnabled($grantType)) {
        throw OAuthServerException::invalidScope($finalized_scope->getIdentifier());
      }
    }

    return $finalized_scopes;
  }

  /**
   * Build a scope entity.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeInterface $scope
   *   The associated scope.
   *
   * @return \League\OAuth2\Server\Entities\ScopeEntityInterface
   *   The initialized scope entity.
   */
  protected function scopeFactory(Oauth2ScopeInterface $scope) {
    return new ScopeEntity($scope);
  }

}
