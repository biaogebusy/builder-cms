<?php

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an interface defining a Private Message thread entity.
 *
 * @ingroup private_message
 */
interface PrivateMessageThreadInterface extends ContentEntityInterface {

  /**
   * Add a member to the private message thread.
   *
   * @param \Drupal\user\AccountInterface $account
   *   The account to be set as a member of the private message thread.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageInterface
   *   Returns the class itself to allow for chaining.
   */
  public function addMember(AccountInterface $account);

  /**
   * Add a member to the private message thread.
   *
   * @param int $id
   *   The ID of the account to be set as a member of the private message
   *   thread.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageInterface
   *   Returns the class itself to allow for chaining.
   */
  public function addMemberById($id);

  /**
   * Retrieve the ids of the members of the private message thread.
   */
  public function getMembersId();

  /**
   * Retrieve the members of the private message thread.
   */
  public function getMembers();

  /**
   * Check if the user with the given ID is a member of the thread.
   *
   * @param int $id
   *   The User ID of the user to check.
   *
   * @return bool
   *   - TRUE if the user is a member of the thread
   *   - FALSE if they are not
   */
  public function isMember($id);

  /**
   * Add a private message to the list of messages in this thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   The private message to be added to the thread.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread
   *   The private message thread.
   */
  public function addMessage(PrivateMessageInterface $privateMessage);

  /**
   * Add a private message by ID to the list of the messages in this thread.
   *
   * @param int $id
   *   The ID of the private message to be added to the thread.
   */
  public function addMessageById($id);

  /**
   * Retrieve all private messages attached to this thread.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface
   *   A list of private messages attached to this thread
   */
  public function getMessages();

  /**
   * Filter the list down to only the newest messages.
   *
   * Note that other messages will be loadable through AJAX.
   */
  public function filterNewestMessages();

  /**
   * Get the created timestamp of the newest private message in the thread.
   *
   * @return int
   *   The Unix timestamp of the newest message in the thread
   */
  public function getNewestMessageCreationTimestamp();

  /**
   * Add a history record to the current thread for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose access time should be updated.
   */
  public function addHistoryRecord(AccountInterface $account);

  /**
   * Get the last access timestamp for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last access time should be retrieved.
   *
   * @return int
   *   The timestamp at which the user last accessed the thread
   */
  public function getLastAccessTimestamp(AccountInterface $account);

  /**
   * Update the last access time for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last access time should be updated.
   */
  public function updateLastAccessTime(AccountInterface $account);

  /**
   * Get the last delete timestamp for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last delete time should be retrieved.
   *
   * @return int
   *   A UNIX timestamp indicating the last time the user marked the thread as
   *   deleted.
   */
  public function getLastDeleteTimestamp(AccountInterface $account);

  /**
   * Update the last delete time for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user whose last delete time should be updated.
   */
  public function updateLastDeleteTime(AccountInterface $account);

  /**
   * Perform a complete delete action on the private message thread including all messages.
   */
  public function delete();

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
   *    (Optional) Account for which thread history will be cleared.
   *    If no account provided, the current user will be used.
   */
  public function clearAccountHistory(AccountInterface $account = NULL);

  /**
   * Filter messages in the thread deleted by the given account.
   *
   * Only messages created after the last time the user deleted the thread will
   * be shown. If they have never deleted the thread, all messages are returned.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for whom private messages should be returned.
   *
   * @return Drupal\private_message\Entity\PrivateMessage[]
   *   An array of private messages
   */
  public function filterUserDeletedMessages(AccountInterface $account);

  /**
   * Clear cache tags related to private message thread entities.
   */
  public function clearCacheTags();

}
