<?php

namespace Drupal\simple_oauth;

/**
 * The OAuth2 scope provider interface.
 */
interface Oauth2ScopeProviderInterface extends Oauth2ScopeAdapterInterface {

  /**
   * Checks if the scope has a permission.
   *
   * @param string $permission
   *   The permission to check for.
   * @param \Drupal\simple_oauth\Oauth2ScopeInterface $scope
   *   The scope to check.
   *
   * @return bool
   *   TRUE if the role has the permission, FALSE if not.
   */
  public function scopeHasPermission(string $permission, Oauth2ScopeInterface $scope): bool;

  /**
   * Gets the roles associated with a scope.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeInterface $scope
   *   The scope to get roles for.
   * @param bool $exclude_locked_roles
   *   If TRUE, locked roles will be excluded from the result.
   *
   * @return string[]
   *   An array of role IDs associated with the scope.
   */
  public function getRoles(Oauth2ScopeInterface $scope, bool $exclude_locked_roles = FALSE): array;

  /**
   * Get the referenced permissions.
   *
   * @return array
   *   Returns the permissions.
   */
  public function getPermissions(Oauth2ScopeInterface $scope): array;

}
