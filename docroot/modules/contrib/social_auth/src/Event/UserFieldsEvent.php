<?php

namespace Drupal\social_auth\Event;

use Drupal\social_auth\User\SocialAuthUserInterface;

/**
 * Defines the user fields to be set in user creation.
 *
 * @todo validate user_fields to be set
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
class UserFieldsEvent extends SocialAuthEventBase {

  /**
   * The user fields.
   *
   * @var array
   */
  protected array $userFields;

  /**
   * The data of the user to be created.
   *
   * @var \Drupal\social_auth\User\SocialAuthUserInterface
   */
  protected SocialAuthUserInterface $user;

  /**
   * UserFieldsEvent constructor.
   *
   * @param array $user_fields
   *   Initial user fields to populate the newly created user.
   * @param string $plugin_id
   *   The plugin ID dispatching this event.
   * @param \Drupal\social_auth\User\SocialAuthUserInterface $user
   *   The data of the user to be created.
   */
  public function __construct(array $user_fields, string $plugin_id, SocialAuthUserInterface $user) {
    $this->userFields = $user_fields;
    $this->pluginId = $plugin_id;
    $this->user = $user;
  }

  /**
   * Gets the user fields.
   *
   * @return array
   *   Fields to initialize for the user creation.
   */
  public function getUserFields(): array {
    return $this->userFields;
  }

  /**
   * Sets the user fields.
   *
   * @param array $user_fields
   *   The user fields.
   */
  public function setUserFields(array $user_fields): void {
    $this->userFields = $user_fields;
  }

  /**
   * Gets the data of the user to be created.
   *
   * @return \Drupal\social_auth\User\SocialAuthUserInterface
   *   The user's data.
   */
  public function getSocialAuthUser(): SocialAuthUserInterface {
    return $this->user;
  }

}
