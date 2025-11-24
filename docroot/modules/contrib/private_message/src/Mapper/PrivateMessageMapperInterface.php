<?php

namespace Drupal\private_message\Mapper;

@trigger_error(__NAMESPACE__ . '\PrivateMessageMapperInterface is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. No replacement is provided. See https://www.drupal.org/node/3490530', E_USER_DEPRECATED);

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;

/**
 * Interface for the Private Message Thread mapper class.
 *
 * @deprecated in private_message:4.0.0 and is removed from
 *   private_message:5.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/node/3490530
 */
interface PrivateMessageMapperInterface {

  /**
   * Retrieve the ID of a thread from the database.
   *
   * The thread returned will contain all of the given UIDs, and only the given
   * UIDs.
   *
   * @param array $uids
   *   An array of User IDs of users whose thread should be
   *   retrieved.
   *
   * @return int|bool
   *   If a thread is found, the thread ID will be returned. Otherwise
   *   FALSE will be returned.
   */
  public function getThreadIdForMembers(array $uids);

  /**
   * Retrieve the ID of the most recently updated thread for the given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently updated thread should be retrieved.
   *
   * @return int|bool
   *   The ID of the most recently updated thread the user is a member of
   *   if one exists, or FALSE if one doesn't.
   */
  public function getFirstThreadIdForUser(UserInterface $user);

  /**
   * Retrieve a list of thread IDs for threads the user belongs to.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently thread IDs should be retrieved.
   * @param mixed $count
   *   The number of thread IDs to retrieve or FALSE to retrieve them all.
   * @param int $timestamp
   *   A timestamp relative to which only thread IDs with an earlier timestamp
   *   should be returned.
   *
   * @return array
   *   An array of thread IDs if any threads exist.
   */
  public function getThreadIdsForUser(UserInterface $user, $count = FALSE, $timestamp = FALSE);

  /**
   * Check if a thread exists after with an ID greater than the given thread ID.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for whom to check.
   * @param int $timestamp
   *   The timestamp to check against.
   *
   * @return bool
   *   TRUE if a previous thread exists, FALSE if one doesn't.
   */
  public function checkForNextThread(UserInterface $user, $timestamp);

  /**
   * Retrieve a list of recently updated private message thread IDs.
   *
   * The last updated timestamp will also be returned. If any ids are provided
   * in $existingThreadIds, the IDs of all threads that have been updated since
   * the oldest updated timestamp for the given thread IDs will be returned.
   * Otherwise the number of IDs returned will be the number provided for
   * $count.
   *
   * @param array $existingThreadIds
   *   An array of thread IDs to be compared against.
   * @param int $count
   *   The number of threads to return if no existing thread IDs were provided.
   *
   * @return array
   *   An array, keyed by thread ID, with each element of the array containing
   *   an object with the following two properties:
   *   - id: The thread ID
   *   - updated: The timestamp at which the thread was last updated
   */
  public function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE);

  /**
   * Get the current user's unread thread count.
   *
   * Retrieves the number of the current user's threads that have been updated
   * since the last time this number was checked.
   *
   * @param int $uid
   *   The user ID of the user whose count should be retrieved.
   * @param int $lastCheckTimestamp
   *   A UNIX timestamp indicating the time after which to check.
   *
   * @return int
   *   The number of threads updated since the given timestamp
   */
  public function getUnreadThreadCount($uid, $lastCheckTimestamp);

  /**
   * Get the current user's unread message count.
   *
   * Retrieves the number of the current user's messages that have been updated
   * since the last time this number was checked.
   *
   * @param int $uid
   *   The user ID of the user whose count should be retrieved.
   * @param int $lastCheckTimestamp
   *   A UNIX timestamp indicating the time after which to check.
   *
   * @return int
   *   The number of threads updated since the given timestamp
   */
  public function getUnreadMessageCount($uid, $lastCheckTimestamp);

  /**
   * Get the current user's unread message count by thread id.
   *
   * Retrieves the number of the current user's messages that have been updated
   * since user last thread access.
   *
   * @param int $uid
   *   The user ID of the user whose count should be retrieved.
   * @param int $thread_id
   *   The thread ID which messages should be checked.
   *
   * @return int
   *   The number of threads updated since the given timestamp
   */
  public function getThreadUnreadMessageCount($uid, $thread_id);

  /**
   * Load the thread id of the thread that a private message belongs to.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   The private message for which the thread ID of the thread it belongs to
   *   should be returned.
   *
   * @return int
   *   The private message thread ID of the thread to which the private message
   *   belongs.
   */
  public function getThreadIdFromMessage(PrivateMessageInterface $privateMessage);

  /**
   * Retrieve the IDs of all threads in the system.
   *
   * @return array
   *   An array of thread IDs for threads in the system.
   */
  public function getThreadIds();

}
