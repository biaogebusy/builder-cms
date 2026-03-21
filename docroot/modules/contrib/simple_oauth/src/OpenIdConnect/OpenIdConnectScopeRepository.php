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
  protected $innerScopeRepository;

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
  public function getScopeEntityByIdentifier($identifier) {
    // First check if this scope exists as a role.
    $role_scope = $this->innerScopeRepository->getScopeEntityByIdentifier($identifier);
    if ($role_scope) {
      return $role_scope;
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
  public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity, $userIdentifier = NULL) {
    $finalized_scopes = $this->innerScopeRepository->finalizeScopes($scopes, $grantType, $clientEntity, $userIdentifier);

    // Make sure that the openid scopes are in the user list.
    $openid_scopes = $this->getOpenIdScopes();
    foreach ($scopes as $scope) {
      if (isset($openid_scopes[$scope->getIdentifier()])) {
        $finalized_scopes = $this->addRoleToScopes($finalized_scopes, new OpenIdConnectScopeEntity($scope->getIdentifier(), $openid_scopes[$scope->getIdentifier()]));
      }
    }
    return $finalized_scopes;
  }

  /**
   * Returns fixed OpenID Connect scopes.
   *
   * @return array
   *   A list of scope names keyed by their identifier.
   */
  protected function getOpenIdScopes() {
    $openid_scopes = [
      'openid' => $this->t('User information'),
      'profile' => $this->t('Profile information'),
      'email' => $this->t('E-Mail'),
      'phone' => $this->t('Phone'),
      'address' => $this->t('Address'),
    ];
    return $openid_scopes;
  }

  /**
   * Add an additional scope if it's not present.
   *
   * @param \League\OAuth2\Server\Entities\ScopeEntityInterface[] $scopes
   *   The list of scopes.
   * @param \League\OAuth2\Server\Entities\ScopeEntityInterface $new_scope
   *   The additional scope.
   *
   * @return \League\OAuth2\Server\Entities\ScopeEntityInterface[]
   *   The modified list of scopes.
   */
  protected function addRoleToScopes(array $scopes, ScopeEntityInterface $new_scope) {
    // Only add the role if it's not already in the list.
    $found = array_filter($scopes, function (ScopeEntityInterface $scope) use ($new_scope) {
      return $scope->getIdentifier() == $new_scope->getIdentifier();
    });
    if (empty($found)) {
      // If it's not there, then add it.
      array_push($scopes, $new_scope);
    }

    return $scopes;
  }

}
