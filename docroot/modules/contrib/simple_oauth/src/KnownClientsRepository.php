<?php

namespace Drupal\simple_oauth;

use Drupal\consumers\Entity\Consumer;
use Drupal\user\UserDataInterface;

/**
 * Default implementation for the known clients repository.
 */
class KnownClientsRepository implements KnownClientsRepositoryInterface {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * KnownClientsRepository constructor.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(UserDataInterface $user_data) {
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorized(int $uid, Consumer $client, array $scopes): bool {
    if (!$client->get('remember_approval')->value) {
      return FALSE;
    }

    $name = 'client:' . $client->getClientId();
    $authorized_scopes = $this->userData->get('simple_oauth', $uid, $name);

    // Access is allowed if all the requested scopes are part of the already
    // authorized scopes.
    if (is_array($authorized_scopes) && !array_diff($scopes, $authorized_scopes)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rememberClient(int $uid, string $client_id, array $scopes) {
    $name = 'client:' . $client_id;
    $existing_scopes = (array) $this->userData->get('simple_oauth', $uid, $name);

    $scopes = array_unique(array_merge($scopes, $existing_scopes));
    $this->userData->set('simple_oauth', $uid, $name, $scopes);
  }

}
