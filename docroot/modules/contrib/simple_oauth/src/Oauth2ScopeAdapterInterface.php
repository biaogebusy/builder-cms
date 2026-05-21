<?php

namespace Drupal\simple_oauth;

/**
 * Adapter interface for the OAuth2 scope.
 */
interface Oauth2ScopeAdapterInterface {

  /**
   * Load scope by id.
   *
   * @param string $id
   *   The scope id.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface|null
   *   Returns the OAuth 2 scope.
   */
  public function load(string $id);

  /**
   * Load multiple scopes by id's.
   *
   * @param array|null $ids
   *   (optional) An array of scope IDs, or NULL to load all scopes.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface[]
   *   An array of scope objects indexed by their ids.
   */
  public function loadMultiple(?array $ids = NULL): array;

  /**
   * Load scope by name.
   *
   * @param string $name
   *   The name of the scope.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface|null
   *   Returns the OAuth 2 scope.
   */
  public function loadByName(string $name): ?Oauth2ScopeInterface;

  /**
   * Load multiple scopes by names.
   *
   * @param array $names
   *   An array of scope names.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface[]
   *   An array of scope objects indexed by their ids.
   */
  public function loadMultipleByNames(array $names): array;

  /**
   * Loads all children of the associated scope.
   *
   * @param string $parent_id
   *   The parent scope id.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface[]
   *   An array of scope objects indexed by their ids.
   */
  public function loadChildren(string $parent_id): array;

  /**
   * Load scopes filtered by grant type.
   *
   * @param string $grant_type
   *   An string of the grant type ID to filter by.
   *
   * @return \Drupal\simple_oauth\Oauth2ScopeInterface[]
   *   An array of scope objects indexed by their ids, filtered by grant type.
   */
  public function loadByGrantType(string $grant_type): array;

}
