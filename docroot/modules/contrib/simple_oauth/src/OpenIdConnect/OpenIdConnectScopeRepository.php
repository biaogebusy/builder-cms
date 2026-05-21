<?php

namespace Drupal\simple_oauth\OpenIdConnect;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_oauth\Entities\OpenIdConnectScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * OpenID Connect scope repository decorator.
 */
class OpenIdConnectScopeRepository implements ScopeRepositoryInterface {

  use StringTranslationTrait;

  /**
   * The inner scope repository.
   *
   * @var \League\OAuth2\Server\Repositories\ScopeRepositoryInterface
   */
  protected ScopeRepositoryInterface $innerScopeRepository;

  /**
   * OpenIdConnectScopeRepository constructor.
   *
   * @param \League\OAuth2\Server\Repositories\ScopeRepositoryInterface $inner_scope_repository
   *   The inner scope repository.
   */
  public function __construct(ScopeRepositoryInterface $inner_scope_repository) {
    $this->innerScopeRepository = $inner_scope_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface {
    // First check if this scope exists.
    $scope = $this->innerScopeRepository->getScopeEntityByIdentifier($identifier);
    if ($scope) {
      return $scope;
    }

    // Fall back to a fixed list of OpenID scopes.
    $openid_scopes = $this->getOpenIdScopes();
    if (isset($openid_scopes[$identifier])) {
      return new OpenIdConnectScopeEntity($identifier, $openid_scopes[$identifier]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, string|null $userIdentifier = NULL, ?string $authCodeId = NULL): array {
    $finalized_scopes = $this->innerScopeRepository->finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier);

    // Make sure that the openid scopes are in the user list.
    $openid_scopes = $this->getOpenIdScopes();
    foreach ($scopes as $scope) {
      if (isset($openid_scopes[$scope->getIdentifier()])) {
        $finalized_scopes = $this->addScope($finalized_scopes, new OpenIdConnectScopeEntity($scope->getIdentifier(), $openid_scopes[$scope->getIdentifier()]));
      }
    }
    return $finalized_scopes;
  }

  /**
   * Returns fixed OpenID Connect scopes.
   *
   * @return array
   *   A list of scope descriptions keyed by their identifier.
   */
  protected function getOpenIdScopes(): array {
    return [
      'openid' => $this->t('View user information'),
      'profile' => $this->t('View profile information'),
      'email' => $this->t('View e-mail'),
      'phone' => $this->t('View phone'),
      'address' => $this->t('View address'),
    ];
  }

  /**
   * Add a scope if it's not present.
   *
   * @param \League\OAuth2\Server\Entities\ScopeEntityInterface[] $scopes
   *   The list of scopes.
   * @param \League\OAuth2\Server\Entities\ScopeEntityInterface $new_scope
   *   The additional scope.
   *
   * @return \League\OAuth2\Server\Entities\ScopeEntityInterface[]
   *   The modified list of scopes.
   */
  protected function addScope(array $scopes, ScopeEntityInterface $new_scope): array {
    // Only add the scope if it's not already in the list.
    $found = array_filter($scopes, function (ScopeEntityInterface $scope) use ($new_scope) {
      return $scope->getIdentifier() == $new_scope->getIdentifier();
    });
    if (empty($found)) {
      // If it's not there, then add it.
      $scopes[] = $new_scope;
    }

    return $scopes;
  }

}
