<?php

namespace Drupal\simple_oauth;

/**
 * An interface that remembers known clients.
 */
interface KnownClientsRepositoryInterface {

  /**
   * Checks if a given user authorized a client for a given set of scopes.
   *
   * @param int $uid
   *   The user ID.
   * @param string $client_id
   *   The client ID.
   * @param string[] $scopes
   *   List of scopes to authorize for.
   *
   * @return bool
   *   TRUE if the client is authorized, FALSE otherwise.
   *
   */
  public function isAuthorized($uid, $client_id, array $scopes);

  /**
   * Store a client with a set of scopes as authorized for a given user.
   *
   * Passed in scopes are merged with already accepted scopes for the given
   * client.
   *
   * @param int $uid
   *   The user ID.
   * @param string $client_id
   *   The client ID.
   * @param string[] $scopes
   *   List of scopes that shuld be authorized.
   */
  public function rememberClient($uid, $client_id, array $scopes);

}
