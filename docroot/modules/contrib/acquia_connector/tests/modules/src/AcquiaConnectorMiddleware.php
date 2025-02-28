<?php

namespace Drupal\acquia_connector_test;

use Drupal\acquia_connector_test\Controller\NspiController;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware for the Acquia Connector API.
 */
class AcquiaConnectorMiddleware {

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $uri = $request->getUri();

        // API requests to NSPI.
        if ($uri->getScheme() . '://' . $uri->getHost() === 'http://mock-spi-server') {
          return $this->createPromise($request);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

  /**
   * Creates a promise for the NSPI request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   Request interface.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   Promise interface.
   *
   * @throws \Exception
   */
  protected function createPromise(RequestInterface $request) {
    $nspiController = new NspiController();
    $path = $request->getUri()->getPath();
    switch ($path) {
      case '/agent-api/subscription':
        $response = $nspiController->getSubscription($request);
        $this->updateRequestCount();
        break;

      case '/spi-api/site':
        $response = $nspiController->nspiUpdate($request);
        $this->updateRequestCount();
        break;

      case '/agent-api/subscription/communication':
        $response = $nspiController->getCommunicationSettings($request);
        $this->updateRequestCount();
        break;

      case '/agent-api/subscription/credentials':
        $response = $nspiController->getCredentials($request);
        $this->updateRequestCount();
        break;

      default:
        // @todo fix problem with adding native route matching and parsing.
        if (strstr($path, '/spi_def/get')) {
          $parts = explode('/', $path);
          $response = $nspiController->spiDefinition($request, $parts[3]);
          $this->updateRequestCount();
        }
        else {
          throw new \Exception("Unhandled path $path");
        }
    }
    return new FulfilledPromise($response);
  }

  /**
   * Update request count.
   */
  protected function updateRequestCount() {
    $requests = \Drupal::state()->get('acquia_connector_test_request_count', 0);
    $requests++;
    \Drupal::state()->set('acquia_connector_test_request_count', $requests);
  }

}
