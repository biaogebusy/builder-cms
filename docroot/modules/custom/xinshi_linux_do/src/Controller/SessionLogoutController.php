<?php

namespace Drupal\xinshi_linux_do\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
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

  protected SessionManagerInterface $sessionManager;

  public function __construct(SessionManagerInterface $session_manager) {
    $this->sessionManager = $session_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('session_manager'));
  }

  /**
   * Destroys every session of the account the bearer token belongs to.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty 204 response.
   */
  public function logout(Request $request): Response {
    // Read before user_logout() resets the current user to anonymous.
    $uid = (int) $this->currentUser()->id();

    // Ends the session this request carries, clears its cookie and fires
    // hook_user_logout. Guarded because session_destroy() warns when no session
    // was ever started, which is the normal case for a bearer-only request.
    if ($request->hasSession() && $request->getSession()->isStarted()) {
      user_logout();
    }

    // The request is authorized by the bearer token, so the session cookie may
    // never arrive (a cache layer stripping Cookie on /api/*, a SameSite rule,
    // an app host that is not same-site with the CMS) and user_logout() can
    // only destroy the session a cookie identifies. Deleting by uid needs no
    // cookie, so it is what actually makes the next /oauth/authorize anonymous.
    // It also closes sessions on other devices, which is the safer reading of
    // "log me out" for a token client.
    if ($uid > 0) {
      $this->sessionManager->delete($uid);
    }

    return new Response(NULL, Response::HTTP_NO_CONTENT);
  }

}
