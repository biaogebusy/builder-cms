<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_server\Functional;

use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Boilerplate for Entity Share Server Functional tests' HTTP requests.
 */
trait EntityShareServerRequestTestTrait {

  use JsonApiRequestTestTrait {
    JsonApiRequestTestTrait::request as parentRequest;
  }

  /**
   * Performs a HTTP request. Wraps the Guzzle HTTP client.
   *
   * Why wrap the Guzzle HTTP client? Because we want to keep the actual test
   * code as simple as possible, and hence not require them to specify the
   * 'http_errors = FALSE' request option, nor do we want them to have to
   * convert Drupal Url objects to strings.
   *
   * We also don't want to follow redirects automatically, to ensure these tests
   * are able to detect when redirects are added or removed.
   *
   * @param string $method
   *   HTTP method.
   * @param \Drupal\Core\Url $url
   *   URL to request.
   * @param array $request_options
   *   Request options to apply.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @see \GuzzleHttp\ClientInterface::request()
   */
  protected function request($method, Url $url, array $request_options) {
    if (!isset($request_options[RequestOptions::HEADERS])) {
      $request_options[RequestOptions::HEADERS] = [];
    }
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    return $this->parentRequest($method, $url, $request_options);
  }

}
