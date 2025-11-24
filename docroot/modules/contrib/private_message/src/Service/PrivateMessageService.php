<?php

declare(strict_types=1);

namespace Drupal\private_message\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * The private message service for the private message module.
 *
 * @todo This service refactoring is postponed to #3489224. To be implemented:
 *   - Replacement of direct calls to database with entity API calls and
 *     removing the $database class property
 *   - Strict typing with respect to BC.
 * @see https://www.drupal.org/project/private_message/issues/3489224
 */
class PrivateMessageService implements PrivateMessageServiceInterface {

  /**
   * The machine name of the private message module.
   */
  private const MODULE_KEY = 'private_message';

  /**
   * The timestamp at which unread private messages were marked as read.
   */
  private const LAST_CHECK_KEY = 'last_notification_check_timestamp';

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly UserDataInterface $userData,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TimeInterface $time,
    protected readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getThreadForMembers(array $members): PrivateMessageThreadInterface {
    $thread_id = $this->getThreadIdForMembers($members);

    if ($thread_id) {
      return $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
    }

    return $this->createPrivateMessageThread($members);
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadForUser(UserInterface $user): PrivateMessageThreadInterface|false {
    $thread_id = $this->getFirstThreadIdForUser($user);
    if ($thread_id) {
      return $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadsForUser(int $count, /* ?int */$timestamp = NULL): array {
    if ($timestamp === FALSE) {
      // @deprecated
      @trigger_error('Passing FALSE to second argument of ' . __METHOD__ . '() is deprecated in private_message:4.0.0 and removed from private_message:5.0.0. Pass NULL instead. See https://www.drupal.org/node/3490530', E_USER_DEPRECATED);
      $timestamp = NULL;
    }

    $return = [
      'threads' => [],
      'next_exists' => FALSE,
    ];

    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());
    $thread_ids = $this->getThreadIdsForUser($user, $count, $timestamp);
    if (count($thread_ids)) {
      $threads = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->loadMultiple($thread_ids);
      if (count($threads)) {
        $last_thread = end($threads);
        $last_timestamp = $last_thread->get('updated')->value;
        $return['next_exists'] = $this->checkForNextThread($user, $last_timestamp);
        $return['threads'] = $threads;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountThreadsForUser(): int {
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());
    $thread_ids = $this->getThreadIdsForUser($user);
    return count($thread_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getNewMessages($threadId, $messageId) {
    $private_message_thread = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->load($threadId);
    if (!$private_message_thread || !$private_message_thread->isMember($this->currentUser->id())) {
      return [];
    }

    $response = [];
    foreach ($private_message_thread->getMessages() as $message) {
      if ($message->id() > $messageId) {
        $response[] = $message;
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousMessages($threadId, $messageId) {
    $return = [
      'messages' => [],
      'next_exists' => FALSE,
    ];

    $start_index = FALSE;
    $slice_count = FALSE;

    $private_message_thread = $this->entityTypeManager
      ->getStorage('private_message_thread')
      ->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($this->currentUser->id());
      $messages = $private_message_thread->filterUserDeletedMessages($user);
      $settings = $this->configFactory->get('core.entity_view_display.private_message_thread.private_message_thread.default')->get('content.private_messages.settings');
      $count = $settings['ajax_previous_load_count'];

      $total = count($messages);
      foreach ($messages as $index => $message) {
        if ($message->id() >= $messageId) {
          $start_index = max($index - $count, 0);
          $slice_count = min($index, $count);
          break;
        }
      }

      if ($start_index !== FALSE && $slice_count !== FALSE) {
        $messages = array_splice($messages, $start_index, $slice_count);
        if (count($messages)) {
          $order = $settings['message_order'];
          if ($order === 'desc') {
            $messages = array_reverse($messages);
          }

          $return['messages'] = $messages;
          $old_messages = $total - ($start_index + $slice_count);
          $return['next_exists'] = ($old_messages + count($messages)) < $total;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreads(array $existingThreadInfo, $count = FALSE) {
    $thread_info = $this->getUpdatedInboxThreadIds(array_keys($existingThreadInfo), $count);
    $new_threads = [];
    $thread_ids = [];
    $ids_to_load = [];
    foreach (array_keys($thread_info) as $thread_id) {
      $thread_ids[] = $thread_id;
      if (!isset($existingThreadInfo[$thread_id]) || $existingThreadInfo[$thread_id] != $thread_info[$thread_id]->updated) {
        $ids_to_load[] = $thread_id;
      }
    }

    if (count($ids_to_load)) {
      $new_threads = $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->loadMultiple($ids_to_load);
    }

    return [
      'thread_ids' => $thread_ids,
      'new_threads' => $new_threads,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->getUnreadThreadCountHelper($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadMessageCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->getUnreadMessageCountHelper($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastCheckTime() {
    $uid = $this->currentUser->id();
    $this->userData->set(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY, $this->time->getRequestTime());

    $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();
    $tags[] = 'private_message:status:uid:' . $this->currentUser->id();

    $this->cacheTagsInvalidator->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function updateThreadAccessTime(PrivateMessageThreadInterface $thread) {
    $thread->updateLastAccessTime($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadFromMessage(PrivateMessageInterface $privateMessage) {
    $thread_id = $this->getThreadIdFromMessage($privateMessage);
    if ($thread_id) {
      return $this->entityTypeManager
        ->getStorage('private_message_thread')
        ->load($thread_id);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createRenderablePrivateMessageThreadLink(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if ($display->getComponent('private_message_link')) {
      if ($entity instanceof UserInterface) {
        $author = $entity;
      }
      else {
        $author = $entity->getOwner();
      }
      $current_user = $this->currentUser;
      if ($current_user->isAuthenticated()) {
        if ($current_user->hasPermission('use private messaging system') && $current_user->id() != $author->id()) {
          $user_entity = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
          $members = [$user_entity, $author];
          $thread_id = $this->getThreadIdForMembers($members);
          if ($thread_id) {
            $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $thread_id], ['attributes' => ['class' => ['private_message_link']]]);
            $build['private_message_link'] = [
              '#type' => 'link',
              '#url' => $url,
              '#title' => t('Send private message'),
              '#prefix' => '<div class="private_message_link_wrapper">',
              '#suffix' => '</div>',
            ];
          }
          else {
            $url = Url::fromRoute('private_message.private_message_create', [], ['query' => ['recipient' => $author->id()]]);
            $build['private_message_link'] = [
              '#type' => 'link',
              '#url' => $url,
              '#title' => t('Send private message'),
              '#prefix' => '<div class="private_message_link_wrapper">',
              '#suffix' => '</div>',
            ];
          }
        }
      }
      else {
        $url = Url::fromRoute('user.login');
        $build['private_message_link'] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => t('Send private message'),
          '#prefix' => '<div class="private_message_link_wrapper">',
          '#suffix' => '</div>',
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIds() {
    return $this->database->select('private_message_threads', 'pmt')
      ->fields('pmt', ['id'])
      ->execute()
      ->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadUnreadMessageCount($uid, $thread_id) {
    // @todo Optimize this, consider deletions and banned users.
    $query = $this->database->select('pm_thread_history', 'pm_thread_history')
      ->condition('uid', $uid)
      ->condition('thread_id', $thread_id);
    $query->join(
      'private_message_thread__private_messages',
      'thread_message',
      'thread_message.entity_id = pm_thread_history.thread_id'
    );
    $query->join(
      'private_messages',
      'messages_data',
      'messages_data.id = thread_message.private_messages_target_id'
    );
    $query->where('[messages_data].[created] > [pm_thread_history].[access_timestamp]');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Create a new private message thread for the given users.
   *
   * @param \Drupal\user\Entity\User[] $members
   *   An array of users who will be members of the given thread.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread
   *   The new private message thread.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPrivateMessageThread(array $members) {
    /** @var \Drupal\private_message\Entity\PrivateMessageThread $thread */
    $thread = $this->entityTypeManager->getStorage('private_message_thread')->create();
    foreach ($members as $member) {
      $thread->addMember($member);
    }

    $thread->save();

    return $thread;
  }

  /**
   * Retrieves the ID of a thread from the database given a list of members.
   *
   * @param \Drupal\user\UserInterface[] $members
   *   A list of users, members of a given thread.
   *
   * @return int|false
   *   The thread ID, if a thread is found, or FALSE.
   */
  protected function getThreadIdForMembers(array $members) {
    $uids = array_map(fn(UserInterface $user) => (int) $user->id(), $members);

    // Select threads common for the given members.
    $query = $this->database->select('private_message_thread__members', 'pmt')
      ->fields('pmt', ['entity_id'])
      ->groupBy('entity_id');
    // Add conditions where the threads are in the set of threads for each of
    // the users.
    foreach ($uids as $uid) {
      $subQuery = $this->database->select('private_message_thread__members', 'pmt')
        ->fields('pmt', ['entity_id'])
        ->condition('members_target_id', $uid);
      $query->condition('entity_id', $subQuery, 'IN');
    }
    $thread_ids = $query->execute()->fetchCol();

    // Exclude threads with other participants.
    foreach ($thread_ids as $thread_id) {
      $query = $this->database->select('private_message_thread__members', 'pmt')
        ->condition('members_target_id', $uids, 'NOT IN')
        ->condition('entity_id', $thread_id);
      if ($query->countQuery()->execute()->fetchField() == 0) {
        return (int) $thread_id;
      }
    }

    return FALSE;
  }

  /**
   * Retrieves the ID of the most recently updated thread for the given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose most recently updated thread should be retrieved.
   *
   * @return int|false
   *   The ID of the most recently updated thread where the user is a member, or
   *   FALSE if one doesn't exist.
   */
  protected function getFirstThreadIdForUser(UserInterface $user) {
    $bannedThreadsQuery = $this->getBannedThreads($user->id());

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->innerJoin('pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->orderBy('thread.updated', 'desc');
    $query->range(0, 1);
    $return = $query->execute()->fetchField();

    return $return !== FALSE ? (int) $return : FALSE;
  }

  /**
   * Retrieves a list of thread IDs for threads the user belongs to.
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
  protected function getThreadIdsForUser(UserInterface $user, $count = FALSE, $timestamp = FALSE): array {
    $bannedThreadsQuery = $this->getBannedThreads($user->id());

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->addExpression('MAX(thread.updated)', 'last_updated');
    $query->innerJoin('pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $user->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');

    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');

    if ($timestamp) {
      $query->condition('updated', $timestamp, '<');
    }

    $query->groupBy('thread.id');
    $query->orderBy('last_updated', 'desc');
    $query->orderBy('thread.id');

    if ($count > 0) {
      $query->range(0, $count);
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Checks if a thread exists after with an ID greater than a given thread ID.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for whom to check.
   * @param int $timestamp
   *   The timestamp to check against.
   *
   * @return bool
   *   TRUE if a previous thread exists, FALSE if one doesn't.
   */
  protected function checkForNextThread(UserInterface $user, $timestamp): bool {
    $query = 'SELECT DISTINCT(thread.id) ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {pm_thread_history} pm_thread_history ' .
      'ON pm_thread_history.thread_id = thread.id AND pm_thread_history.uid = :history_uid ' .
      'JOIN {private_message_thread__members} AS thread_member ' .
      'ON thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'WHERE pm_thread_history.delete_timestamp <= messages.created ' .
      'AND thread.updated < :timestamp';
    $vars = [
      ':uid' => $user->id(),
      ':history_uid' => $user->id(),
      ':timestamp' => $timestamp,
    ];

    return (bool) $this->database->queryRange(
      $query,
      0, 1,
      $vars
    )->fetchField();
  }

  /**
   * Retrieves a list of recently updated private message thread IDs.
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
  protected function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE): array {
    $bannedThreadsQuery = $this->getBannedThreads($this->currentUser->id());

    $query = $this->database->select('private_message_threads', 'thread');
    $query->addField('thread', 'id');
    $query->addField('thread', 'updated');
    $query->innerJoin('pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.uid = :uid', [':uid' => $this->currentUser->id()]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $this->currentUser->id()]);
    $query->innerJoin('private_message_thread__private_messages', 'thread_messages', 'thread_messages.entity_id = thread.id');
    $query->innerJoin('private_messages', 'messages', 'messages.id = thread_messages.private_messages_target_id AND thread_history.delete_timestamp <= messages.created');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->orderBy('thread.updated', 'desc');
    $query->groupBy('thread.id');

    if (count($existingThreadIds)) {
      $subquery = $this->database->select('private_message_threads', 'thread');
      $subquery->addExpression('MIN(updated)');
      $subquery->condition('id', $existingThreadIds, 'IN');

      $query->condition('thread.updated', $subquery, '>=');
    }
    else {
      $query->range(0, $count);
    }

    return $query->execute()->fetchAllAssoc('id');
  }

  /**
   * Gets the current user's unread thread count.
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
  protected function getUnreadThreadCountHelper($uid, $lastCheckTimestamp) {
    $bannedThreadsQuery = $this->getBannedThreads($uid);

    $query = $this->database->select('private_messages', 'message');
    $query->addField('thread', 'id');
    $query->innerJoin('private_message_thread__private_messages', 'thread_message', 'message.id = thread_message.private_messages_target_id');
    $query->innerJoin('private_message_threads', 'thread', 'thread_message.entity_id = thread.id');
    $query->innerJoin('pm_thread_history', 'thread_history', 'thread_history.thread_id = thread.id AND thread_history.access_timestamp < thread.updated AND thread_history.uid = :uid', [':uid' => $uid]);
    $query->innerJoin('private_message_thread__members', 'thread_member', 'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid', [':uid' => $uid]);
    $query->condition('thread.updated', $lastCheckTimestamp, '>');
    $query->condition('message.created', $lastCheckTimestamp, '>');
    $query->condition('message.owner', $uid, '<>');
    $query->condition('thread.id', $bannedThreadsQuery, 'NOT IN');
    $query->groupBy('thread.id');

    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets the current user's unread message count.
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
  protected function getUnreadMessageCountHelper($uid, $lastCheckTimestamp) {
    $bannedThreadsQuery = $this->getBannedThreads($uid);

    $query = $this->database->select('private_messages', 'message');
    $query->join(
      'private_message_thread__private_messages',
      'thread_message',
      'message.id = thread_message.private_messages_target_id'
    );
    $query->join(
      'private_message_threads',
      'thread',
      'thread_message.entity_id = thread.id'
    );
    $query->join(
      'pm_thread_history',
      'thread_history',
      'thread_history.thread_id = thread.id AND thread_history.uid = :uid',
      [':uid' => $uid]
    );
    $query->join(
      'private_message_thread__members',
      'thread_member',
      'thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid',
      [':uid' => $uid]
    );
    $query
      ->condition('thread.updated ', $lastCheckTimestamp, '>')
      ->condition('message.created', $lastCheckTimestamp, '>')
      ->condition('message.owner', $uid, '<>')
      ->condition('thread.id', $bannedThreadsQuery, 'NOT IN')
      ->where('thread_history.access_timestamp < thread.updated');
    $query = $query->countQuery();
    return $query->execute()->fetchField();
  }

  /**
   * Loads the thread id of the thread that a private message belongs to.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   The private message for which the thread ID of the thread it belongs to
   *   should be returned.
   *
   * @return int
   *   The private message thread ID of the thread to which the private message
   *   belongs.
   */
  protected function getThreadIdFromMessage(PrivateMessageInterface $privateMessage) {
    $query = $this->database->select('private_message_threads', 'thread');
    $query->fields('thread', ['id']);
    $query->join('private_message_thread__private_messages',
      'messages',
      'messages.entity_id = thread.id AND messages.private_messages_target_id = :message_id',
      [':message_id' => $privateMessage->id()]
    );
    return $query
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns query object of banned threads for the user.
   *
   * @param int|string $user_id
   *   The user id.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query object.
   */
  protected function getBannedThreads(int|string $user_id): SelectInterface {
    // Get the list of banned users for this user.
    $subquery = $this->database->select('private_message_ban', 'pmb');
    $subquery->addField('pmb', 'target');
    $subquery->condition('pmb.owner', $user_id);

    // Get list of threads with banned users.
    $bannedThreadsQuery = $this->database->select('private_message_thread__members', 'thread_member');
    $bannedThreadsQuery->addField('thread_member', 'entity_id');
    $bannedThreadsQuery->condition('thread_member.members_target_id', $subquery, 'IN');
    $bannedThreadsQuery->groupBy('entity_id');

    return $bannedThreadsQuery;
  }

}
