<?php

namespace Drupal\simple_oauth_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Debug route for the implicit grant.
 */
class RedirectDebug extends ControllerBase {

  /**
   * Debug the token response for the implicit grant.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function token(Request $request) {
    return new JsonResponse($request->getRequestUri());
  }

}
