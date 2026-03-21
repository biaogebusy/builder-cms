<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\consumers\Entity\ConsumerInterface;

interface Oauth2GrantManagerInterface {

  /**
   * Gets the authorization server.
   *
   * @param string $grant_type
   *   The grant type used as plugin ID.
   * @param \Drupal\consumers\Entity\ConsumerInterface|null $client
   *   The consumer entity. May be NULL for BC.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   When the grant cannot be found.
   *
   * @return \League\OAuth2\Server\AuthorizationServer
   *   The authorization server.
   */
  public function getAuthorizationServer($grant_type, ConsumerInterface $client = NULL);

}
