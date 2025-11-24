<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\private_message\Entity\PrivateMessageBanInterface;

/**
 * The Private Message Ban manager service.
 */
class PrivateMessageBanManager implements PrivateMessageBanManagerInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isBanned(int|string $user_id): bool {
    $select = $this
      ->database
      ->select('private_message_ban', 'pmb');
    $select
      ->addExpression('1');

    return (bool) $select->condition('pmb.owner', $this->currentUser->id())
      ->condition('pmb.target', $user_id)
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrentUserBannedByUser(int $user_id): bool {
    @trigger_error(__METHOD__ . '() is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. No replacement is provided. See https://www.drupal.org/node/3490530', E_USER_DEPRECATED);

    $select = $this
      ->database
      ->select('private_message_ban', 'pmb');
    $select
      ->addExpression('1');

    return (bool) $select->condition('pmb.owner', $user_id)
      ->condition('pmb.target', $this->currentUser->id())
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getBannedUsers(int|string $user_id): array {
    return $this
      ->database
      ->select('private_message_ban', 'pmb')
      ->fields('pmb', ['target'])
      ->condition('pmb.owner', $user_id)
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function unbanUser(int|string $user_id): void {
    $ban = $this->findBanEntity((int) $this->currentUser->id(), (int) $user_id);
    if (!$ban) {
      // The user is not banned; just return.
      return;
    }

    // Delete the ban and display a message.
    $ban->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function banUser(int|string $user_id): void {
    $ban = $this->findBanEntity((int) $this->currentUser->id(), (int) $user_id);
    if ($ban) {
      // The user is already banned; just return.
      return;
    }

    // Create the ban and display a message.
    $this->entityTypeManager
      ->getStorage('private_message_ban')
      ->create([
        'owner' => $this->currentUser->id(),
        'target' => $user_id,
      ])
      ->save();
  }

  /**
   * Finds the ban entity for the given owner and target.
   *
   * @param int $owner
   *   The ban entity owner.
   * @param int $user_id
   *   The ID of user being banned.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageBanInterface|null
   *   The ban entity or NULL if not found.
   */
  protected function findBanEntity(int $owner, int $user_id): ?PrivateMessageBanInterface {
    // Find the ban entity.
    $bans = $this->entityTypeManager
      ->getStorage('private_message_ban')
      ->loadByProperties([
        'owner' => $owner,
        'target' => $user_id,
      ]);

    // reset() returns the first element of the array or FALSE if the array is
    // empty, but we want the return value to be NULL if the array is empty.
    $ban = reset($bans) ?: NULL;
    assert($ban === NULL || $ban instanceof PrivateMessageBanInterface);
    return $ban;
  }

}
