<?php

namespace Drupal\simple_oauth;

use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;

/**
 * Defines the interface for OAuth2 Scope.
 *
 * @ingroup simple_oauth
 */
interface Oauth2ScopeInterface {

  /**
   * The permission granularity.
   */
  const GRANULARITY_PERMISSION = 'permission';

  /**
   * The role granularity.
   */
  const GRANULARITY_ROLE = 'role';

  /**
   * Get the scope id.
   *
   * @return string
   *   Returns the scope id.
   */
  public function id();

  /**
   * Get the scope name.
   *
   * @return string
   *   Returns the scope name.
   */
  public function getName(): string;

  /**
   * Get the scope description.
   *
   * @return string
   *   Returns the scope description.
   */
  public function getDescription(): string;

  /**
   * Get the grant types on the scope.
   *
   * @return array
   *   Returns the grant types.
   */
  public function getGrantTypes(): array;

  /**
   * Get the associated description by grant type.
   *
   * @param string $grant_type
   *   The grant type to retrieve description from.
   *
   * @return string|null
   *   Returns the grant type description.
   */
  public function getGrantTypeDescription(string $grant_type): ?string;

  /**
   * Checks if a grant type is enabled.
   *
   * @param string $grant_type
   *   The grant type id.
   *
   * @return bool
   *   Returns true/false.
   */
  public function isGrantTypeEnabled(string $grant_type): bool;

  /**
   * Is an umbrella scope.
   *
   * @return bool
   *   Returns true/false.
   */
  public function isUmbrella(): bool;

  /**
   * Get the parent scope.
   *
   * @return string|null
   *   Returns the parent scope.
   */
  public function getParent(): ?string;

  /**
   * Get the granularity plugin.
   *
   * @return ?\Drupal\simple_oauth\Plugin\ScopeGranularityInterface
   *   Returns the granularity plugin, generally permission or role.
   */
  public function getGranularity(): ?ScopeGranularityInterface;

  /**
   * Get the referenced permissions.
   *
   * @return array
   *   Returns the permissions.
   */
  public function getPermissions(): array;

}
