<?php

namespace Drupal\simple_oauth\Server;

use League\OAuth2\Server\ResourceServer;

/**
 * Resource Server factory interface.
 */
interface ResourceServerFactoryInterface {

  /**
   * Get the Resource Server.
   *
   * @return \League\OAuth2\Server\ResourceServer
   *   Returns the League Resource Server.
   */
  public function get(): ResourceServer;

}
