<?php

namespace Drupal\simple_oauth;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Session\AccountInterface;
use Drupal\consumers\Entity\Consumer;

/**
 * Service in charge of deleting or expiring tokens that cannot be used anymore.
 */
class ExpiredCollector {

  /**
   * The token storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $tokenStorage;

  /**
   * The client storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $clientStorage;

  /**
   * The date time to collect tokens.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $dateTime;

  /**
   * ExpiredCollector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The date time service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $date_time) {
    $this->clientStorage = $entity_type_manager->getStorage('consumer');
    $this->tokenStorage = $entity_type_manager->getStorage('oauth2_token');
    $this->dateTime = $date_time;
  }

  /**
   * Collect all expired token ids.
   *
   * @param int $limit
   *   Number of tokens to fetch.
   *
   * @return \Drupal\simple_oauth\Entity\Oauth2TokenInterface[]
   *   The expired tokens.
   */
  public function collect(int $limit = 0): array {
    $query = $this->tokenStorage->getQuery();
    $query->accessCheck();
    $query->condition('expire', $this->dateTime->getRequestTime(), '<');
    // If limit available.
    if (!empty($limit)) {
      $query->range(0, $limit);
    }
    if (!$results = $query->execute()) {
      return [];
    }
    return array_values($this->tokenStorage->loadMultiple(array_values($results)));
  }

  /**
   * Collect all the tokens associated with the provided account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\simple_oauth\Entity\Oauth2TokenInterface[]
   *   The tokens.
   */
  public function collectForAccount(AccountInterface $account): array {
    $clients = [];
    $output = [];
    $query = $this->tokenStorage->getQuery();
    $query->accessCheck();
    $query->condition('auth_user_id', $account->id());
    $query->condition('bundle', 'refresh_token', '!=');
    $entity_ids = $query->execute();
    $output = $entity_ids
      ? array_values($this->tokenStorage->loadMultiple(array_values($entity_ids)))
      : [];
    // Also collect the tokens of the clients that have this account as the
    // default user.
    try {
      $clients = array_values($this->clientStorage->loadByProperties([
        'user_id' => $account->id(),
      ]));
    }
    catch (QueryException $exception) {
      return $output;
    }
    // Append all the tokens for each of the clients having this account as the
    // default.
    $tokens = array_reduce($clients, function ($carry, $client) {
      return array_merge($carry, $this->collectForClient($client));
    }, $output);
    // Return a unique list.
    $existing = [];
    foreach ($tokens as $token) {
      $existing[$token->id()] = $token;
    }
    return array_values($existing);
  }

  /**
   * Collect all the tokens associated a particular client.
   *
   * @param \Drupal\consumers\Entity\Consumer $client
   *   The account.
   * @param bool $include_refresh
   *   Include refresh tokens.
   *
   * @return \Drupal\simple_oauth\Entity\Oauth2TokenInterface[]
   *   The tokens.
   */
  public function collectForClient(Consumer $client, bool $include_refresh = FALSE): array {
    $query = $this->tokenStorage->getQuery();
    $query->accessCheck();
    $query->condition('client', $client->id());
    // Only exclude refresh tokens if not explicitly included.
    if (!$include_refresh) {
      $query->condition('bundle', 'refresh_token', '!=');
    }
    if (!$entity_ids = $query->execute()) {
      return [];
    }
    /** @var \Drupal\simple_oauth\Entity\Oauth2TokenInterface[] $results */
    $results = $this->tokenStorage->loadMultiple(array_values($entity_ids));
    return array_values($results);
  }

  /**
   * Deletes multiple tokens based on ID.
   *
   * @param \Drupal\simple_oauth\Entity\Oauth2TokenInterface[] $tokens
   *   The token entity IDs.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteMultipleTokens(array $tokens = []) {
    $this->tokenStorage->delete($tokens);
  }

}
