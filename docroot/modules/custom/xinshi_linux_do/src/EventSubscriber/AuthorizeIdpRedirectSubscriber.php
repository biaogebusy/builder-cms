<?php

namespace Drupal\xinshi_linux_do\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Intercepts /oauth/authorize?idp=linux_do for anonymous users.
 *
 * Stashes the original authorize query as a destination and redirects the
 * user to /user/login/linux_do, which kicks off the linux.do OAuth dance.
 * After linux.do callback finalizes a Drupal session, the user is brought
 * back to /oauth/authorize where simple_oauth completes the code flow.
 */
class AuthorizeIdpRedirectSubscriber implements EventSubscriberInterface {

  const SUPPORTED_IDP = 'linux_do';

  protected AccountInterface $currentUser;
  protected RouteMatchInterface $routeMatch;

  public function __construct(AccountInterface $current_user, RouteMatchInterface $route_match) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after the router is matched but before the simple_oauth controller.
    // ROUTER priority is 32 (RouterListener::onKernelRequest); we run at 30.
    return [
      KernelEvents::REQUEST => [['onRequest', 30]],
    ];
  }

  /**
   * Intercept the request when it matches the authorize route + idp param.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'oauth2_token.authorize') {
      return;
    }
    if ($request->query->get('idp') !== self::SUPPORTED_IDP) {
      return;
    }
    if (!$this->currentUser->isAnonymous()) {
      // Already logged in: let simple_oauth proceed (it will issue a code).
      return;
    }

    // Preserve the entire authorize query (including idp) so that, after the
    // linux.do flow finalizes a Drupal session, the browser comes back here
    // and simple_oauth issues the code.
    $authorize_query = $request->getQueryString() ?? '';
    $destination = '/oauth/authorize' . ($authorize_query !== '' ? '?' . $authorize_query : '');

    // Use a custom query key (not 'destination') because Drupal core's
    // RedirectResponseSubscriber rewrites any RedirectResponse Location with
    // the 'destination' query param, which would short-circuit the redirect
    // to linux.do and bounce the user straight back to /oauth/authorize.
    $url = Url::fromRoute('xinshi_linux_do.user.login', [], [
      'query' => ['return_to' => $destination],
    ])->toString();

    $event->setResponse(new RedirectResponse($url));
  }

}
