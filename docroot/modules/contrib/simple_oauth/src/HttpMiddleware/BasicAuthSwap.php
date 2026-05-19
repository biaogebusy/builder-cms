<?php

namespace Drupal\simple_oauth\HttpMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Uses the basic auth information to provide the client credentials for OAuth2.
 */
class BasicAuthSwap implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a BasicAuthSwap object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   *
   * If the request appears to be an OAuth2 token request with Basic Auth,
   * swap the Basic Auth credentials into the request body and then remove the
   * Basic Auth credentials from the request so that core authentication is
   * not performed later.
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    if (
      strpos($request->getPathInfo(), '/oauth/token') !== FALSE &&
      $request->headers->has('PHP_AUTH_USER') &&
      $request->headers->has('PHP_AUTH_PW')
    ) {
      // Swap the Basic Auth credentials into the request data.
      $request->request->set('client_id', $request->headers->get('PHP_AUTH_USER'));
      $request->request->set('client_secret', $request->headers->get('PHP_AUTH_PW'));

      // Remove the Basic Auth credentials to prevent later authentication.
      $request->headers->remove('PHP_AUTH_USER');
      $request->headers->remove('PHP_AUTH_PW');
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
