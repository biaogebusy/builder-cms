<?php

namespace Drupal\private_message\Service;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserInterface;

/**
 * The interface for the Private Message Service.
 */
interface PrivateMessageServiceInterface {

  /**
   * The machine name of the private message module.
   */
  const MODULE_KEY = 'private_message';

  /**
   * The timestamp at which unread private messages were marked as read.
   */
  const LAST_CHECK_KEY = 'last_notification_check_timestamp';

  /**
   * Retrieves the private message thread for the given members.
   *
   * If no thread exists, one will be created.
   *
   * @param \Drupal\user\UserInterface[] $members
   *   An array of User objects for whom the private message
   *   thread should be retrieved.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread
   *   A private message thread that contains all members in the thread.
   */
  public function getThreadForMembers(array $members);

  /**
   * Get the most recently updated thread for the given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently updated thread should be retrieved.
   */
  public function getFirstThreadForUser(UserInterface $user);

  /**
   * Retrieve private message threads for a given user.
   *
   * @param int $count
   *   The number of threads to retrieve.
   * @param int $timestamp
   *   A timestamp relative to which only threads with an earlier timestamp
   *   should be returned.
   *
   * @return array
   *   An array with two keys:
   *   - threads: an array of
   *     \Drupal\private_message\Entity\PrivateMessageThread
   *   - next_exists: a boolean indicating whether any more private message
   *     threads exist after the last one
   */
  public function getThreadsForUser($count, $timestamp = FALSE);

  /**
   * Retrieve the number of threads a user has.
   *
   * @return int
   *   The number of threads a user has.
   */
  public function getCountThreadsForUser();

  /**
   * Retrieve a users private messages created after the given ID.
   *
   * @param int $threadId
   *   The ID of the thread from which messages should be retrieved.
   * @param int $messageId
   *   The ID after which messages should be retrieved.
   *
   * @return array
   *   An array containing any new messages that the user has permission to view
   */
  public function getNewMessages($threadId, $messageId);

  /**
   * Retrieve old messages for a user that were created before the given ID.
   *
   * @param int $threadId
   *   The ID of the thread from which messages should be retrieved.
   * @param int $messageId
   *   The ID before which messages should be retrieved.
   *
   * @return array
   *   An array containing the following to keys:
   *   - messages: an array of \Drupal\private_message\Entity\PrivateMessage
   *   - next_exists: a boolean indicating whether or not any messages appear
   *     in the thread before the first message returned
   */
  public function getPreviousMessages($threadId, $messageId);

  /**
   * Get a list of User objects whose account names begin with the given string.
   *
   * Only accounts that have 'Use private messaging system' permission will be
   * returned, and the viewing user must have both 'View user information' and
   * 'access user profiles' to get any results at all.
   *
   * @param string $string
   *   The string to search for.
   * @param int $count
   *   The maximum number of results to return.
   *
   * @return \Drupal\user\Entity\User[]
   *   An array of User accounts whose account names begin with the given
   *   string.
   */
  public function getUsersFromString($string, $count);

  /**
   * Retrieve inbox information for the current user.
   *
   * If $existingThreadIds is provided it means that the inbox already has
   * threads in it. Any threads that have changed since the provided last update
   * time, as well as any new threads that have been updated since the oldest
   * provided updated time, will be returned. If $existingThreadIds is not
   * provided it means the inbox is empty, so a maximum of $count number of
   * threads is instead returned.
   *
   * @param array $existingThreadIds
   *   An array of private message thread last update times, keyed by thread ID.
   * @param int $count
   *   The number of threads to return if no existing thread IDs were provided.
   *
   * @return array
   *   An array containing the following keys:
   *   - thread_ids: The IDs of any threads in the inbox, in the order in which
   *     they should appear in the inbox. This array will be used to (re) order
   *     inbox items
   *   - new_threads: An array of
   *     \Drupal\private_message\Entity\PrivateMessageThread objects for any
   *     threads that either don't exist in the DOM at the time of the request,
   *     or do exist but have been updated.
   */
  public function getUpdatedInboxThreads(array $existingThreadIds, $count = FALSE);

  /**
   * Determine whether given user is allowed to be used in a  thread.
   *
   * Usernames belonging to accounts that have the 'use private messaging
   * system' permission will be considered valid.
   *
   * @param string $username
   *   The username to be validated.
   *
   * @return bool
   *   - TRUE if the belongs to an account that has the 'use private messaging
   *     system' permission
   *   - FALSE if the account doesn't exist, or does not have the required
   *     permission
   */
  public function validatePrivateMessageMemberUsername($username);

  /**
   * Get the current user's unread threads count.
   *
   * @return int
   *   The number of updated threads
   */
  public function getUnreadThreadCount();

  /**
   * Get the current user's unread messages count.
   *
   * @return int
   *   The number of updated threads
   */
  public function getUnreadMessageCount();

  /**
   * Marks a timestamp at which all threads are considered read.
   */
  public function updateLastCheckTime();

  /**
   * Updates the last access time for the current user on the given thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The thread whose access time should be updated.
   */
  public function updateThreadAccessTime(PrivateMessageThreadInterface $thread);

  /**
   * Load the thread that a private message belongs to.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   The private message for which the thread it belongs to should be
   *   returned.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread
   *   The private message thread to which the private message belongs
   */
  public function getThreadFromMessage(PrivateMessageInterface $privateMessage);

  /**
   * Add a link to send a private message to the owner of the given entity.
   *
   * @param array $build
   *   A render array representing the given entity. Chances should be made to
   *   this render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display being used to render the entity.
   * @param string $view_mode
   *   The view mode being used to render the entity.
   */
  public function createRenderablePrivateMessageThreadLink(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode);

  /**
   * Retrieve the IDs of all threads in the system.
   *
   * @return array
   *   An array of thread IDs for threads in the system.
   */
  public function getThreadIds();

}
