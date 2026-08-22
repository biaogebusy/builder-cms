<?php

namespace Drupal\xinshi_linux_do\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\xinshi_linux_do\LinuxDoSDK;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Endpoints for linux.do OAuth2 federated login.
 */
class LinuxDoAuthController extends ControllerBase {

  protected LinuxDoSDK $sdk;

  public function __construct(LinuxDoSDK $sdk) {
    $this->sdk = $sdk;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('xinshi_linux_do.sdk'));
  }

  /**
   * Entry point: generate state and redirect to linux.do authorize URL.
   */
  public function login(Request $request) {
    // Only allow internal destinations. We require the destination to start
    // with /oauth/authorize to avoid open redirect via the social flow.
    $destination = $request->query->get('return_to');
    if ($destination && !$this->isSafeAuthorizeDestination($destination)) {
      $this->getLogger('xinshi_linux_do')->warning('Rejected unsafe destination: @d', ['@d' => $destination]);
      $destination = NULL;
    }

    $state = $this->sdk->startState($destination);
    $authorize_url = $this->sdk->buildAuthorizeUrl($state);
    return new TrustedRedirectResponse($authorize_url);
  }

  /**
   * Callback endpoint registered with linux.do.
   */
  public function callback(Request $request) {
    $code = $request->query->get('code');
    $state = $request->query->get('state');

    if (empty($code) || empty($state)) {
      throw new BadRequestHttpException('Missing code or state.');
    }
    if (!$this->sdk->consumeState($state)) {
      throw new BadRequestHttpException('Invalid or expired state.');
    }

    $token_data = $this->sdk->exchangeCodeForToken($code);
    if (empty($token_data['access_token'])) {
      throw new BadRequestHttpException('Token exchange failed.');
    }

    $profile = $this->sdk->fetchProfile($token_data['access_token']);
    if (empty($profile)) {
      throw new BadRequestHttpException('Failed to fetch linux.do profile.');
    }

    [$account, $reason] = $this->sdk->mapProfileToUser($profile);
    if ($account === NULL) {
      if ($reason === 'email_bound_to_different_linux_do_id') {
        $this->messenger()->addError($this->t('该邮箱已绑定其它 linux.do 账号，无法重新绑定。'));
      }
      else {
        $this->messenger()->addError($this->t('linux.do 登录失败，请联系管理员。'));
      }
      return new RedirectResponse(Url::fromRoute('user.login')->toString());
    }

    // Establish a Drupal session.
    user_login_finalize($account);

    // Resume the original /oauth/authorize flow if a destination was stashed.
    $destination = $this->sdk->consumeDestination();
    if ($destination && $this->isSafeAuthorizeDestination($destination)) {
      return new RedirectResponse($destination);
    }

    // Fallback: front page.
    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

  /**
   * Access check for /user/login/linux_do.
   */
  public function loginAccess(AccountInterface $account): AccessResultInterface {
    $config = $this->config(LinuxDoSDK::CONFIG_NAME);
    return AccessResult::allowedIf(
      $account->isAnonymous() && (bool) $config->get('login_activate')
    )->addCacheTags($config->getCacheTags());
  }

  /**
   * Whitelist the post-callback destination to /oauth/authorize on this site.
   */
  protected function isSafeAuthorizeDestination(string $destination): bool {
    // Reject absolute external URLs.
    if (UrlHelper::isExternal($destination)) {
      return FALSE;
    }
    $parts = parse_url($destination);
    return ($parts !== FALSE) && (($parts['path'] ?? '') === '/oauth/authorize');
  }

}
