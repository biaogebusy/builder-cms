<?php

namespace Drupal\social_auth\Event;

use Drupal\social_auth\User\SocialAuthUserInterface;
use Drupal\user\UserInterface;

/**
 * Dispatched when user is created or logged in through Social Auth.
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
class UserEvent extends SocialAuthEventBase {

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * The user's data passed by Social Auth.
   *
   * @var \Drupal\social_auth\User\SocialAuthUserInterface|null
   */
  protected ?SocialAuthUserInterface $socialAuthUser;

  /**
   * UserEvent constructor.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param string $plugin_id
   *   The plugin Id dispatching this event.
   * @param \Drupal\social_auth\User\SocialAuthUserInterface|null $social_auth_user
   *   The user's data passed by Social Auth.
   */
  public function __construct(UserInterface $user, string $plugin_id, ?SocialAuthUserInterface $social_auth_user = NULL) {
    $this->user = $user;
    $this->pluginId = $plugin_id;
    $this->socialAuthUser = $social_auth_user;
  }

  /**
   * Gets the user.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * Gets user's data passed by Social Auth.
   *
   * @return \Drupal\social_auth\User\SocialAuthUserInterface
   *   The user's data.
   */
  public function getSocialAuthUser(): SocialAuthUserInterface {
    return $this->socialAuthUser;
  }

}
