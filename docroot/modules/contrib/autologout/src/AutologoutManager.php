<?php

namespace Drupal\autologout;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\UserData;
use Drupal\Component\Utility\Xss;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines an AutologoutManager service.
 */
class AutologoutManager implements AutologoutManagerInterface {

  use StringTranslationTrait;

  /**
   * The module manager service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config object for 'autologout.settings'.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $autoLogoutSettings;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The session.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $session;

  /**
   * Data of the user.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs an AutologoutManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Data of the user.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger service.
   * @param \Drupal\Core\Session\SessionManager $sessionManager
   *   The session.
   * @param \Drupal\user\UserData $userData
   *   Data of the user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    AccountInterface $current_user,
    LoggerChannelFactoryInterface $logger,
    SessionManager $sessionManager,
    UserData $userData,
    TimeInterface $time,
    EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation
  ) {
    $this->moduleHandler = $module_handler;
    $this->autoLogoutSettings = $config_factory->get('autologout.settings');
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->logger = $logger->get('autologout');
    $this->session = $sessionManager;
    $this->userData = $userData;
    $this->time = $time;
    $this->entityTypeManager = $entityTypeManager;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public function preventJs() {
    if ($this->autoLogoutSettings->get('enabled') === FALSE) {
      // Autologout is disabled globally.
      return TRUE;
    }

    foreach ($this->moduleHandler->invokeAll('autologout_prevent') as $prevent) {
      if (!empty($prevent)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshOnly() {
    foreach ($this->moduleHandler->invokeAll('autologout_refresh_only') as $module_refresh_only) {
      if (!empty($module_refresh_only)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function inactivityMessage() {
    $message = Xss::filter($this->autoLogoutSettings->get('inactivity_message'));
    $type = $this->autoLogoutSettings->get('inactivity_message_type');
    if (!empty($message)) {
      $this->messenger->addMessage($this->t('@message', ['@message' => $message]), $type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logout() {
    $user = $this->currentUser;
    if ($this->autoLogoutSettings->get('use_watchdog')) {
      $this->logger->info(
        'Session automatically closed for %name by autologout.',
        ['%name' => $user->getAccountName()]
      );
    }

    // Destroy the current session.
    $this->moduleHandler->invokeAll('user_logout', [$user]);
    $this->session->destroy();
    $user->setAccount(new AnonymousUserSession());
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleTimeout() {
    $roles = user_roles(TRUE);
    $role_timeout = [];

    // Go through roles, get timeouts for each and return as array.
    foreach ($roles as $name => $role) {
      $role_settings = $this->configFactory->get('autologout.role.' . $name);
      if ($role_settings->get('enabled')) {
        $timeout_role = $role_settings->get('timeout');
        $role_timeout[$name] = $timeout_role;
      }
    }

    return $role_timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleUrl() {
    $roles = user_roles(TRUE);
    $role_url = [];

    // Go through roles, get timeouts for each and return as array.
    foreach ($roles as $name => $role) {
      $role_settings = $this->configFactory->get('autologout.role.' . $name);
      if ($role_settings->get('enabled')) {
        $url_role = $role_settings->get('url');
        $role_url[$name] = $url_role;
      }
    }
    return $role_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingTime() {
    if ($this->configFactory->get('logout_regardless_of_activity')) {
      $time_passed = $this->time->getRequestTime() - $_COOKIE['Drupal_visitor_autologout_login'];
    }
    else {
      $time_passed = isset($_SESSION['autologout_last'])
        ? $this->time->getRequestTime() - $_SESSION['autologout_last']
        : 0;
    }
    $timeout = $this->getUserTimeout();
    return $timeout - $time_passed;
  }

  /**
   * {@inheritdoc}
   */
  public function createTimer() {
    return $this->getRemainingTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getUserTimeout($uid = NULL) {
    if (is_null($uid)) {
      // If $uid is not provided, use the logged in user.
      $user = $this->currentUser;
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')
        ->load($uid);
    }

    if ($user->id() == 0) {
      // Anonymous doesn't get logged out.
      return 0;
    }
    $user_timeout = $this->userData->get('autologout', $user->id(), 'timeout');

    if (is_numeric($user_timeout)) {
      // User timeout takes precedence.
      return $user_timeout;
    }

    // Get role timeouts for user.
    if ($this->autoLogoutSettings->get('role_logout')) {
      $user_roles = $user->getRoles();
      $output = [];
      $timeouts = $this->getRoleTimeout();
      foreach ($user_roles as $rid => $role) {
        if (isset($timeouts[$role])) {
          $output[$rid] = $timeouts[$role];
        }
      }

      // Assign the lowest/highest timeout value to be session timeout value.
      if (!empty($output)) {
        // If one of the user's roles has a unique timeout, use this.
        if ($this->autoLogoutSettings->get('role_logout_max')) {
          return max($output);
        }
        else {
          return min($output);
        }
      }
    }

    // If no user or role override exists, return the default timeout.
    return $this->autoLogoutSettings->get('timeout');
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRedirectUrl($uid = NULL) {
    if (is_null($uid)) {
      // If $uid is not provided, use the logged in user.
      $user = $this->entityTypeManager->getStorage('user')
        ->load($this->currentUser->id());
    }
    else {
      $user = $this->entityTypeManager->getStorage('user')
        ->load($uid);
    }

    if ($user->id() == 0) {
      // Anonymous doesn't get logged out.
      return;
    }

    // Get role timeouts for user.
    if ($this->autoLogoutSettings->get('role_logout')) {
      $user_roles = $user->getRoles();
      $output = [];
      $urls = $this->getRoleUrl();
      foreach ($user_roles as $rid => $role) {
        if (isset($urls[$role])) {
          $output[$rid] = $urls[$role];
        }
      }

      // Assign the first matching Role.
      if (!empty($output) && !empty(reset($output))) {
        // If one of the user's roles has a unique URL, use this.
        return reset($output);
      }
    }

    // If no user or role override exists, return the default timeout.
    return $this->autoLogoutSettings->get('redirect_url');
  }

  /**
   * {@inheritdoc}
   */
  public function logoutRole(UserInterface $user) {
    if ($this->autoLogoutSettings->get('role_logout')) {
      foreach ($user->roles as $name => $role) {
        if ($this->configFactory->get('autologout.role.' . $name . '.enabled')) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
