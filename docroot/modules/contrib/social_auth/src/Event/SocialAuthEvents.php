<?php

namespace Drupal\social_auth\Event;

/**
 * Defines Social Auth Events constants.
 */
final class SocialAuthEvents {

  /**
   * Dispatched event when social auth is gathering user fields.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\UserFieldsEvent
   *
   * @var string
   */
  const USER_FIELDS = 'social_auth.user.fields';

  /**
   * Dispatched event when a new user is created via social auth.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\UserEvent
   *
   * @var string
   */
  const USER_CREATED = 'social_auth.user.created';

  /**
   * Dispatched event when user logs in using social auth.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\UserEvent
   *
   * @var string
   */
  const USER_LOGIN = 'social_auth.user.login';

  /**
   * Dispatched event before redirecting to provider.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\BeforeRedirectEvent
   *
   * @var string
   */
  const BEFORE_REDIRECT = 'social_auth.before_redirect';

  /**
   * Dispatched event after authentication fails in provider.
   *
   * @Event
   *
   * @see \Drupal\social_auth\Event\FailedAuthenticationEvent
   *
   * @var string
   */
  const FAILED_AUTH = 'social_auth.failed_authentication';

}
