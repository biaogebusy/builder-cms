<?php

namespace Drupal\flood_control;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a base class Flood Unblock actions.
 */
abstract class FloodUnblockManagerBase implements FloodUnblockManagerInterface {

  use StringTranslationTrait;

  /**
   * The Flood Interface.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The Immutable Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Entity Type Manager Interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function canFilter() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents() {
    return [
      'user.failed_login_ip' => [
        'type' => 'ip',
        'label' => $this->t('User failed login IP'),
      ],
      'user.failed_login_user' => [
        'type' => 'user',
        'label' => $this->t('User failed login user'),
      ],
      'user.http_login' => [
        'type' => 'user',
        'label' => $this->t('User failed http login'),
      ],
      'user.password_request_ip' => [
        'type' => 'user',
        'label' => $this->t('User failed password request IP'),
      ],
      'user.password_request_user' => [
        'type' => 'user',
        'label' => $this->t('User failed password request user'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEventLabel($event) {
    $event_mapping = $this->getEvents();
    if (array_key_exists($event, $event_mapping)) {
      return $event_mapping[$event]['label'];
    }

    return ucfirst(str_replace(['.', '_'], ' ', $event));
  }

  /**
   * {@inheritdoc}
   */
  public function getEventType($event) {
    $event_mapping = $this->getEvents();
    if (array_key_exists($event, $event_mapping)) {
      return $event_mapping[$event]['type'];
    }

    $parts = explode('.', $event);
    return $parts[0];
  }

  /**
   * {@inheritdoc}
   */
  public function fetchIdentifiers(array $results) {

    $identifiers = [];

    foreach ($results as $result) {

      // Sets ip as default value and adds to identifiers array.
      $identifiers[$result] = $result;

      // Sets location as value and adds to identifiers array.
      if (function_exists('smart_ip_get_location')) {
        $location = smart_ip_get_location($result);
        $location_string = sprintf(" (%s %s %s)", $location['city'], $location['region'], $location['country_code']);
        $identifiers[$result] = "$location_string ($result)";
      }

      // Sets link to user as value and adds to identifiers array.
      $parts = explode('-', $result);
      if (isset($parts[0]) && isset($parts[1])) {
        $uid = $parts[0];

        /** @var \Drupal\user\Entity\User $user */
        $user = $this->entityTypeManager->getStorage('user')
          ->load($uid);
        if (isset($user)) {
          $user_link = $user->toLink($user->getAccountName());
        }
        else {
          $user_link = $this->t('Deleted user: @user', ['@user' => $uid]);
        }
        $identifiers[$result] = $user_link;
      }

    }
    return $identifiers;
  }

  /**
   * {@inheritdoc}
   */
  public function isBlocked($identifier, $event) {
    $type = $this->getEventType($event);
    switch ($type) {
      case 'user':
        return !$this->flood->isAllowed($event, $this->config->get('user_limit'), $this->config->get('user_window'), $identifier);

      case 'ip':
        return !$this->flood->isAllowed($event, $this->config->get('ip_limit'), $this->config->get('ip_window'), $identifier);
    }
    return FALSE;
  }

}
