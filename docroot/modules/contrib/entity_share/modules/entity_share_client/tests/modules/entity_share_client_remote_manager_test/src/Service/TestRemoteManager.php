<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client_remote_manager_test\Service;

use Drupal\entity_share_client\Service\RemoteManager;
use GuzzleHttp\ClientInterface;

/**
 * Service that allows to emulate another website in tests.
 *
 * @package Drupal\entity_share_client_remote_manager_test\Service
 */
class TestRemoteManager extends RemoteManager {

  /**
   * A mapping, URL => response, from the GET requests made.
   *
   * @var \Psr\Http\Message\ResponseInterface[]
   */
  protected $responseMapping = [];

  /**
   * {@inheritdoc}
   */
  protected function doRequest(ClientInterface $client, $method, $url, array $options = []) {
    // If it is a GET request store the result to be able to re-obtain the
    // result to simulate another website.
    if ($method == 'GET') {
      if (!isset($this->responseMapping[$url])) {
        $this->responseMapping[$url] = parent::doRequest($client, $method, $url);
      }

      return $this->responseMapping[$url];
    }

    return parent::doRequest($client, $method, $url, $options);
  }

  /**
   * Clear the response mapping.
   *
   * This is useful if it is needed to emulate a runtime change of content
   * on server.
   */
  public function resetResponseMapping() {
    $this->responseMapping = [];
  }

  /**
   * Clear the HTTP clients caching.
   *
   * This is useful if it is needed to emulate a runtime change of remote.
   *
   * @param string $type
   *   Whether to reset JSON:API or regular HTTP clients cache.
   */
  public function resetHttpClientsCache(string $type) {
    switch ($type) {
      case 'json_api':
        $this->jsonApiHttpClients = [];
        break;

      default:
        $this->httpClients = [];
        break;
    }
  }

  /**
   * Clear the remote info caching.
   *
   * This is useful if it is needed to emulate a runtime change of remote.
   */
  public function resetRemoteInfos() {
    $this->remoteInfos = [];
  }

}
