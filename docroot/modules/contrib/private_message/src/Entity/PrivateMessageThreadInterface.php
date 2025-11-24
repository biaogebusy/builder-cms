<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface defining a Private Message thread entity.
 */
interface PrivateMessageThreadInterface extends ContentEntityInterface {

  /**
   * Adds a member to the private message thread.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to be set as a member of the private message thread.
   *
   * @return $this
   */
  public function addMember(AccountInterface $account): self;

  /**
   * Adds a member to the private message thread by its ID.
   *
   * @param int|string $id
   *   The ID of the user to be set as a member of the private message thread.
   *
   * @return $this
   */
  public function addMemberById(int|string $id): self;

  /**
   * Retrieves the IDs of the members of the private message thread.
   *
   * @return int[]
   *   A list of member IDs
   */
  public function getMemberIds(): array;

  /**
   * Retrieves the IDs of the members of the private message thread.
   *
   * @return int[]
   *   A list of member IDs
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Use self::getMemberIds() instead.
   *
   * @see https://www.drupal.org/node/3490530
   */
  public function getMembersId(): array;

  /**
   * Retrieves the members of the private message thread.
   *
   * @return \Drupal\user\UserInterface[]
   *   A list of members user accounts.
   */
  public function getMembers(): array;

  /**
   * Checks if the user with the given ID is a member of the thread.
   *
   * @param int|string $id
   *   The User ID of the user to check.
   *
   * @return bool
   *   Whether the user is member of the thread.
   */
  public function isMember(int|string $id): bool;

  /**
   * Adds a private message to the list of messages in this thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   The private message to be added to the thread.
   *
   * @return $this
   */
  public function addMessage(PrivateMessageInterface $privateMessage): self;

  /**
   * Adds a private message by ID to the list of the messages in this thread.
   *
   * @param int|string $id
   *   The ID of the private message to be added to the thread.
   *
   * @return $this
   */
  public function addMessageById(int|string $id): self;

  /**
   * Retrieves all private messages attached to this thread.
   *
   * @param bool $includeBlocked
   *   (optional) Include messages from blocked users? Defaults to FALSE.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageInterface[]
   *   A list of private messages attached to this thread
   */
  public function getMessages(bool $includeBlocked = FALSE): array;

  /**
   * Filters the list down to only the newest messages.
   *
   * Note that other messages will be loadable through AJAX.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. No replacement is provided.
   *
   * @see https://www.drupal.org/node/3490530
   */
  public function filterNewestMessages();

  /**
   * Gets the created timestamp of the newest private message in the thread.
   *
   * @return int
   *   The Unix timestamp of the newest message in the thread
   */
  public function getNewestMessageCreationTimestamp(): int;

  /**
   * Adds a history record to the current thread for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose access time should be updated.
   */
  public function addHistoryRecord(AccountInterface $account): void;

  /**
   * Gets the last access timestamp for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last access time should be retrieved.
   *
   * @return int
   *   The timestamp at which the user last accessed the thread
   */
  public function getLastAccessTimestamp(AccountInterface $account): int;

  /**
   * Updates the last access time for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last access time should be updated.
   *
   * @return $this
   */
  public function updateLastAccessTime(AccountInterface $account): self;

  /**
   * Gets the last delete timestamp for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last delete time should be retrieved.
   *
   * @return int
   *   A UNIX timestamp indicating the last time the user marked the thread as
   *   deleted.
   */
  public function getLastDeleteTimestamp(AccountInterface $account): int;

  /**
   * Updates the last delete time for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last delete time should be updated.
   *
   * @return $this
   */
  public function updateLastDeleteTime(AccountInterface $account): self;

  /**
   * Provides clear thread history feature.
   *
   * The following steps will happen:
   *   - The delete timestamp for the given user is updated
   *   - The created timestamp for the newest message in the
   *     thread is retrieved
   *   - The delete timestamps for all members of the thread
   *     are compared to the timestamp of the newest private message.
   *   - If no messages have been created after every member has deleted
   *     the thread, the entire thread is deleted from the system.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (Optional) Account for which thread history will be cleared.
   *   If no account provided, the current user will be used.
   */
  public function clearAccountHistory(?AccountInterface $account = NULL): void;

  /**
   * Filters messages in the thread deleted by the given account.
   *
   * Only messages created after the last time the user deleted the thread will
   * be shown. If they have never deleted the thread, all messages are returned.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for whom private messages should be returned.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageInterface[]
   *   An array of private messages
   */
  public function filterUserDeletedMessages(AccountInterface $account): array;

  /**
   * Returns the thread last updated time.
   *
   * @return int
   *   Thread last updated time.
   */
  public function getUpdatedTime(): int;

  /**
   * Clears cache tags related to private message thread entities.
   */
  public function clearCacheTags(): void;

}
