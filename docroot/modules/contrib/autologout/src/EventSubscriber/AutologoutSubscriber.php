<?php

namespace Drupal\autologout\EventSubscriber;

use Drupal\autologout\AutologoutManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines autologout Subscriber.
 */
class AutologoutSubscriber implements EventSubscriberInterface {

  /**
   * The autologout manager service.
   *
   * @var \Drupal\autologout\AutologoutManagerInterface
   */
  protected $autoLogoutManager;

  /**
   * The user account service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Config service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $theme;

  /**
   * The Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The request stacks service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs an AutologoutSubscriber object.
   *
   * @param \Drupal\autologout\AutologoutManagerInterface $autologout
   *   The autologout manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account service.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The Config service.
   * @param \Drupal\Core\Theme\ThemeManager $theme
   *   The theme manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(AutologoutManagerInterface $autologout, AccountInterface $account, ConfigFactory $config, ThemeManager $theme, TimeInterface $time, RequestStack $requestStack, LanguageManagerInterface $language_manager) {
    $this->autoLogoutManager = $autologout;
    $this->currentUser = $account;
    $this->config = $config;
    $this->theme = $theme;
    $this->time = $time;
    $this->requestStack = $requestStack;
    $this->languageManager = $language_manager;
  }

  /**
   * Check for autologout JS.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {
    $autologout_manager = $this->autoLogoutManager;

    $uid = $this->currentUser->id();

    if ($uid == 0) {
      $autologout_timeout = $this->requestStack->getCurrentRequest()->query->get('autologout_timeout');
      $post = $this->requestStack->getCurrentRequest()->request->all();
      if (!empty($autologout_timeout) && $autologout_timeout == 1 && empty($post)) {
        $autologout_manager->inactivityMessage();
      }
      return;
    }

    // If user is not anonymous.
    if ($uid != 0) {
      $session = $this->requestStack->getCurrentRequest()->getSession();
      $auto_redirect = $session->get('auto_redirect');

      // Get http referer.
      $referer = "";
      $request = $this->requestStack->getCurrentRequest();
      if ($request->server->get('HTTP_REFERER')) {
        $referer = $request->server->get('HTTP_REFERER');
      }
      // Get query string from http referer url.
      $parse_url = parse_url($referer, PHP_URL_QUERY);
      // If http referer url has 'destination' and session is not set,
      // then only redirect to user page if uid dosen't match.
      if ($parse_url !== NULL && (strpos($parse_url, 'destination') !== FALSE) && empty($auto_redirect)) {
        parse_str($parse_url, $output);
        $destination_uid = explode("/", $output['destination']);

        // If array contains language code, remove it.
        $languagecode = $this->languageManager->getCurrentLanguage()->getId();
        if ($destination_uid[1] === $languagecode) {
          unset($destination_uid[1]);
          $destination_uid = array_values($destination_uid);
        }

        // If destination uid and actual uid does not match then,
        // redirect to loggedin user page.
        if (($destination_uid[1] == "user") && ($destination_uid[2] != $uid)) {
          $auto_redirect = $session->set('auto_redirect', 1);
          $login_url = Url::fromRoute('user.page', [], ['absolute' => TRUE])->toString();

          // Redirect user to user page.
          $response = new RedirectResponse($login_url);
          $event->setResponse($response);
        }
      }
    }

    if ($this->autoLogoutManager->preventJs()) {
      return;
    }

    $now = $this->time->getRequestTime();
    // Check if anything wants to be refresh only. This URL would include the
    // javascript but will keep the login alive whilst that page is opened.
    $refresh_only = $autologout_manager->refreshOnly();
    $timeout = $autologout_manager->getUserTimeout();
    $timeout_padding = $this->config->get('autologout.settings')->get('padding');
    $is_altlogout = strpos($event->getRequest()->getRequestUri(), '/autologout_alt_logout') !== FALSE;

    // We need a backup plan if JS is disabled.
    if (!$is_altlogout && !$refresh_only && isset($_SESSION['autologout_last'])) {
      // If time since last access is > timeout + padding, log them out.
      $diff = $now - $_SESSION['autologout_last'];
      if ($diff >= ($timeout + (int) $timeout_padding)) {
        $autologout_manager->logout();
        // User has changed so force Drupal to remake decisions based on user.
        global $theme, $theme_key;
        drupal_static_reset();
        $theme = NULL;
        $theme_key = NULL;
        $this->theme->getActiveTheme();
        $autologout_manager->inactivityMessage();
      }
      else {
        $_SESSION['autologout_last'] = $now;
      }
    }
    else {
      $_SESSION['autologout_last'] = $now;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];

    return $events;
  }

}
