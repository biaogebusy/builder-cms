<?php

namespace Drupal\simple_oauth\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining Access Token entities.
 *
 * @ingroup simple_oauth
 */
interface Oauth2TokenInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Revoke a token.
   */
  public function revoke();

  /**
   * Check if the token was revoked.
   *
   * @return bool
   *   TRUE if the token is revoked. FALSE otherwise.
   */
  public function isRevoked(): bool;

  /**
   * Checks whether a certain permission is set via the scopes.
   *
   * @param string $permission
   *   The permission string to check.
   *
   * @return bool
   *   TRUE if the token has the permission, FALSE otherwise.
   */
  public function hasPermission(string $permission): bool;

  /**
   * Get the roles associated with this token.
   *
   * @param bool $exclude_locked_roles
   *   If TRUE, locked roles will be excluded from the result.
   *
   * @return string[]
   *   An array of role IDs associated with this token.
   */
  public function getRoles(bool $exclude_locked_roles = FALSE): array;

}
