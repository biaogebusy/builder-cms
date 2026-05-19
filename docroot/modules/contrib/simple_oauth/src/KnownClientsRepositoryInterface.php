<?php

namespace Drupal\simple_oauth;

use Drupal\consumers\Entity\Consumer;

/**
 * An interface that remembers known clients.
 */
interface KnownClientsRepositoryInterface {

  /**
   * Checks if a given user authorized a client for a given set of scopes.
   *
   * @param int $uid
   *   The user ID.
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The consumer entity.
   * @param string[] $scopes
   *   List of scopes to authorize for.
   *
   * @return bool
   *   TRUE if the client is authorized, FALSE otherwise.
   */
  public function isAuthorized(int $uid, Consumer $client, array $scopes): bool;

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
   *   List of scopes that should be authorized.
   */
  public function rememberClient(int $uid, string $client_id, array $scopes);

}
