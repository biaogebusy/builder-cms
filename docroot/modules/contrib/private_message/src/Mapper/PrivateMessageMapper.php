<?php

namespace Drupal\private_message\Mapper;

@trigger_error(__NAMESPACE__ . '\PrivateMessageMapper is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. No replacement is provided. See https://www.drupal.org/node/3490530', E_USER_DEPRECATED);

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;

/**
 * Interface for the Private Message Mapper class.
 *
 * @deprecated in private_message:4.0.0 and is removed from
 *   private_message:5.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/node/3490530
 */
class PrivateMessageMapper implements PrivateMessageMapperInterface {

  public function __construct(
    protected readonly Connection $database,
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getThreadIdForMembers(array $members) {
    $uids = [];
    foreach ($members as $member) {
      $uids[$member->id()] = $member->id();
    }

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
        return $thread_id;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadIdForUser(UserInterface $user) {

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
    return $query->execute()->fetchField();

  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdsForUser(UserInterface $user, $count = FALSE, $timestamp = FALSE): array {

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
   * {@inheritdoc}
   */
  public function checkForNextThread(UserInterface $user, $timestamp): bool {
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
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE): array {

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
   * {@inheritdoc}
   */
  public function getUnreadThreadCount($uid, $lastCheckTimestamp): int {

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
   * {@inheritdoc}
   */
  public function getUnreadMessageCount($uid, $lastCheckTimestamp) {
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
   * {@inheritdoc}
   */
  public function getThreadIdFromMessage(PrivateMessageInterface $privateMessage): int {
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
   * {@inheritdoc}
   */
  public function getThreadIds(): array {
    return $this->database->select('private_message_threads', 'pmt')
      ->fields('pmt', ['id'])
      ->execute()
      ->fetchCol();
  }

  /**
   * Returns query object of banned threads for the user.
   *
   * @param int $user_id
   *   The user id.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query object.
   */
  protected function getBannedThreads(int $user_id): SelectInterface {
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
