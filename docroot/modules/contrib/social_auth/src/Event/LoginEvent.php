<?php

declare(strict_types=1);

namespace Drupal\social_auth\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\social_auth\User\SocialAuthUserInterface;

/**
 * Provides the event class for login event.
 */
final class LoginEvent extends SocialAuthEventBase {

  /**
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private AccountInterface $drupalAccount;

  /**
   * The social auth user.
   *
   * @var \Drupal\social_auth\User\SocialAuthUserInterface
   */
  private SocialAuthUserInterface $socialAuthUser;

  /**
   * Constructs a LoginEvent.
   *
   * @param \Drupal\Core\Session\AccountInterface $drupal_user
   *   The Drupal account.
   * @param \Drupal\social_auth\User\SocialAuthUserInterface $social_auth_user
   *   The social auth user.
   * @param string $plugin_id
   *   The plugin ID of the provider used for login.
   */
  public function __construct(
    AccountInterface $drupal_user,
    SocialAuthUserInterface $social_auth_user,
    string $plugin_id,
  ) {
    $this->drupalAccount = $drupal_user;
    $this->socialAuthUser = $social_auth_user;
    $this->pluginId = $plugin_id;
  }

  /**
   * Gets the Drupal account associated with the event.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Drupal account.
   */
  public function getDrupalAccount(): AccountInterface {
    return $this->drupalAccount;
  }

  /**
   * Gets the Social Auth user associated with the event.
   *
   * @return \Drupal\social_auth\User\SocialAuthUserInterface
   *   Social Auth user.
   */
  public function getSocialAuthUser(): SocialAuthUserInterface {
    return $this->socialAuthUser;
  }

}
