<?php

namespace Drupal\entity_share_client\Http;

use GuzzleHttp\HandlerStack;

/**
 * Interface for the http client factory service.
 *
 * Allows mocking in tests.
 *
 * @todo Remove this when https://www.drupal.org/project/drupal/issues/3463251
 * is fixed.
 *
 * @internal
 */
interface ClientFactoryInterface {

  /**
   * Constructs a new client object from some configuration.
   *
   * @param array $config
   *   The config for the client.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  public function fromOptions(array $config = []);

}
