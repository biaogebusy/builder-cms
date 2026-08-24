<?php

namespace Drupal\xinshi_linux_do\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the Drupal session that /oauth/authorize opened for an API client.
 *
 * The SPA authenticates with a bearer token and has no way to close that
 * session on its own: core's user.logout requires a CSRF token derived from
 * the session itself, and /session/token only mints the one for the
 * X-CSRF-Token header, so a token-less GET is answered with 403 and a redirect
 * to the confirm form. A session that outlives the app's own logout makes the
 * next /oauth/authorize hand out a code for the account that just left, which
 * is why a federated ?idp= login silently returns the previous user instead of
 * redirecting to the provider.
 *
 * @see \Drupal\xinshi_linux_do\EventSubscriber\AuthorizeIdpRedirectSubscriber
 */
class SessionLogoutController extends ControllerBase {

  /**
   * Destroys the current session and resets the user to anonymous.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty 204 response.
   */
  public function logout(): Response {
    user_logout();
    return new Response(NULL, Response::HTTP_NO_CONTENT);
  }

}
