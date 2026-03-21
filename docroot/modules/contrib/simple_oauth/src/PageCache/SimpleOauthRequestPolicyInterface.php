<?php

namespace Drupal\simple_oauth\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The interface for determining the requests with OAuth data.
 *
 * The service that implements the interface is used to determine whether
 * the page should be served from cache and also if the request contains
 * an access token to proceed to the authentication.
 *
 * @see \Drupal\simple_oauth\PageCache\DisallowSimpleOauthRequests::check()
 * @see \Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider::applies()
 */
interface SimpleOauthRequestPolicyInterface extends RequestPolicyInterface {

  /**
   * Returns a state whether the request has an OAuth2 access token.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool
   *   A state whether the request has an OAuth2 access token.
   */
  public function isOauth2Request(Request $request);

}
