<?php

namespace Drupal\xinshi_sms\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\simple_oauth\Controller\Oauth2AuthorizeController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sends anonymous authorize requests to the SMS login form.
 *
 * The decoupled client signs people up with a phone number and never issues a
 * password, so simple_oauth's default hop to /user/login asks those accounts
 * for a credential they do not have — a dead end with no way forward. When the
 * SMS login is active this points them at /user/signin instead and keeps
 * `destination`, so the authorization request resumes once they are signed in.
 *
 * The parent's "An external client application is requesting access to your
 * data" notice is intentionally left out here: on a first-party login the
 * client is this very site, and the warning only alarms the user.
 *
 * Site requirement: when the require_login module is enabled, /user/signin has
 * to sit in its excluded paths (its own PROTECTED_ROUTES list only knows core's
 * login routes). Otherwise require_login answers /user/signin with a redirect
 * to /user/login, core's RedirectResponseSubscriber rewrites that Location to
 * the `destination` this hop carries, and the browser bounces between
 * /oauth/authorize and /user/signin until it aborts with too many redirects.
 */
class OtpAwareAuthorizeController extends Oauth2AuthorizeController {

  /**
   * {@inheritdoc}
   */
  protected function redirectAnonymous(Request $request): RedirectResponse {
    if (!$this->config('xinshi_sms.settings')->get('activate')) {
      return parent::redirectAnonymous($request);
    }
    $destination = Url::fromRoute('oauth2_token.authorize', [], [
      'query' => UrlHelper::parse('/?' . $request->getQueryString())['query'],
    ]);
    $url = Url::fromRoute('xinshi_sms.otp_login_form', [], [
      'query' => ['destination' => $destination->toString()],
    ]);
    return new RedirectResponse($url->toString());
  }

}
