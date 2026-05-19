<?php

namespace Drupal\simple_oauth\Server;

use Drupal\consumers\Entity\Consumer;
use League\OAuth2\Server\AuthorizationServer;

/**
 * Authorization Server factory interface.
 */
interface AuthorizationServerFactoryInterface {

  /**
   * Get the Authorization Server based on the client.
   *
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The consumer entity.
   *
   * @return \League\OAuth2\Server\AuthorizationServer
   *   Returns the League Authorization Server.
   *
   * @throws \Exception
   */
  public function get(Consumer $client): AuthorizationServer;

}
