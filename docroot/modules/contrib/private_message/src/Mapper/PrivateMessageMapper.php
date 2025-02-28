<?php

namespace Drupal\private_message\Mapper;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;

/**
 * Interface for the Private Message Mapper class.
 */
class PrivateMessageMapper implements PrivateMessageMapperInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a PrivateMessageMapper object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(Connection $database, AccountProxyInterface $currentUser) {
    $this->database = $database;
    $this->currentUser = $currentUser;
  }

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
    return $this->database->queryRange('SELECT thread.id ' .
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
      'ORDER BY thread.updated DESC',
      0,
      1,
      [
        ':uid' => $user->id(),
        ':history_uid' => $user->id(),
      ]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdsForUser(UserInterface $user, $count = FALSE, $timestamp = FALSE) {
    $query = 'SELECT DISTINCT(thread.id), MAX(thread.updated) ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {pm_thread_history} pm_thread_history ' .
      'ON pm_thread_history.thread_id = thread.id AND pm_thread_history.uid = :history_uid ' .
      'JOIN {private_message_thread__members} AS thread_member ' .
      'ON thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'WHERE pm_thread_history.delete_timestamp <= messages.created ';
    $vars = [':uid' => $user->id(), ':history_uid' => $user->id()];

    if ($timestamp) {
      $query .= 'AND updated < :timestamp ';
      $vars[':timestamp'] = $timestamp;
    }

    $query .= 'GROUP BY thread.id ORDER BY MAX(thread.updated) DESC, thread.id';

    if ($count > 0) {
      $thread_ids = $this->database->queryRange(
        $query,
        0, $count,
        $vars
      )->fetchCol();
    }
    else {
      $thread_ids = $this->database->query(
        $query,
        $vars
      )->fetchCol();
    }

    return is_array($thread_ids) ? $thread_ids : [];
  }

  /**
   * {@inheritdoc}
   */
  public function checkForNextThread(UserInterface $user, $timestamp) {
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
  public function getUserIdsFromString($string, $count) {
    if ($this->currentUser->hasPermission('access user profiles') && $this->currentUser->hasPermission('use private messaging system')) {
      $arguments = [
        ':string' => $string . '%',
        ':current_user' => $this->currentUser->getAccountName(),
        ':authenticated_config' => 'user.role.authenticated',
      ];
      $query = 'SELECT user_data.uid FROM {users_field_data} AS user_data LEFT ' .
        'JOIN {user__roles} AS user_roles ' .
        'ON user_roles.entity_id = user_data.uid ' .
        'LEFT JOIN {config} AS role_config ' .
        "ON role_config.name = CONCAT('user.role.', user_roles.roles_target_id) " .
        'JOIN {config} AS config ON config.name = :authenticated_config ' .
        'WHERE user_data.name LIKE :string AND user_data.name != :current_user ';

      $rids = $this->getCanUseRids();
      if (!in_array('authenticated', $rids)) {
        $arguments[':rids[]'] = $rids;
        $query .= 'AND user_roles.roles_target_id IN (:rids[]) ';
      }
      $query .= 'ORDER BY user_data.name ASC';

      return $this->database->queryRange(
        $query,
        0,
        $count,
        $arguments
      )->fetchCol();

      return $results;
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreadIds(array $existingThreadIds, $count = FALSE) {
    $query = 'SELECT DISTINCT(thread.id), updated ' .
      'FROM {private_message_threads} AS thread ' .
      'JOIN {private_message_thread__members} AS thread_member ' .
      'ON thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid ' .
      'JOIN {pm_thread_history} pm_thread_history ' .
      'ON pm_thread_history.thread_id = thread.id AND pm_thread_history.uid = :history_uid ' .
      'JOIN {private_message_thread__private_messages} AS thread_messages ' .
      'ON thread_messages.entity_id = thread.id ' .
      'JOIN {private_messages} AS messages ' .
      'ON messages.id = thread_messages.private_messages_target_id ' .
      'WHERE pm_thread_history.delete_timestamp <= messages.created ';
    $vars = [':uid' => $this->currentUser->id(), ':history_uid' => $this->currentUser->id()];
    $order_by = 'ORDER BY thread.updated DESC';
    if (count($existingThreadIds)) {
      $query .= 'AND thread.updated >= (SELECT MIN(updated) FROM {private_message_threads} WHERE id IN (:ids[])) ';
      $vars[':ids[]'] = $existingThreadIds;

      return $this->database->query($query . $order_by, $vars)->fetchAllAssoc('id');
    }
    else {
      return $this->database->queryRange($query . $order_by, 0, $count, $vars)->fetchAllAssoc('id');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkPrivateMessageMemberExists($username) {
    $arguments = [
      ':username' => $username,
      ':authenticated_user_role' => 'user.role.authenticated',
    ];

    $query = 'SELECT 1 FROM {users_field_data} AS user_data ' .
      'LEFT JOIN {user__roles} AS user_roles ' .
      'ON user_roles.entity_id = user_data.uid ' .
      'LEFT JOIN {config} AS role_config ' .
      "ON role_config.name = CONCAT('user.role.', user_roles.roles_target_id) " .
      'LEFT JOIN {config} AS authenticated_config ' .
      'ON authenticated_config.name = :authenticated_user_role ' .
      'WHERE user_data.name = :username ';

    $rids = $this->getCanUseRids();
    if (!in_array('authenticated', $rids)) {
      $arguments[':rids[]'] = $rids;
      $query .= 'AND user_roles.roles_target_id IN (:rids[]) ';
    }
    $query .= 'AND user_data.status = 1';

    return $this->database->queryRange($query,
      0,
      1,
      $arguments
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount($uid, $lastCheckTimestamp) {
    return $this->database->query(
      'SELECT COUNT(DISTINCT thread.id) FROM {private_messages} AS message ' .
      'JOIN {private_message_thread__private_messages} AS thread_message ' .
      'ON message.id = thread_message.private_messages_target_id ' .
      'JOIN {private_message_threads} AS thread ' .
      'ON thread_message.entity_id = thread.id ' .
      'JOIN {pm_thread_history} AS thread_history ' .
      'ON thread_history.thread_id = thread.id AND thread_history.uid = :uid ' .
      'JOIN {private_message_thread__members} AS thread_member ' .
      'ON thread_member.entity_id = thread.id AND thread_member.members_target_id = :uid ' .
      'WHERE thread.updated > :timestamp AND message.created > :timestamp AND message.owner <> :uid AND thread_history.access_timestamp < thread.updated ',
      [
        ':uid' => $uid,
        ':timestamp' => $lastCheckTimestamp,
      ]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadMessageCount($uid, $lastCheckTimestamp) {
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
      ->where('thread_history.access_timestamp < thread.updated');
    $query = $query->countQuery();
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIdFromMessage(PrivateMessageInterface $privateMessage) {
    return $this->database->queryRange(
      'SELECT thread.id FROM {private_message_threads} AS thread JOIN ' .
      '{private_message_thread__private_messages} AS messages ' .
      'ON messages.entity_id = thread.id AND messages.private_messages_target_id = :message_id',
      0,
      1,
      [
        ':message_id' => $privateMessage->id(),
      ]
    )->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadIds() {
    return $this->database->query('SELECT id FROM {private_message_threads}')->fetchCol();
  }

  /**
   * Returns role ids with permission to use PM system.
   *
   * @return int[]|string[]
   *   Array of role IDs.
   */
  protected function getCanUseRids() {
    $use_pm_permission = 'use private messaging system';
    $roles = user_role_names(FALSE, $use_pm_permission);
    return array_keys($roles);
  }

}
