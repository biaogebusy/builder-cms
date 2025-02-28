<?php

namespace Drupal\private_message\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Mapper\PrivateMessageMapperInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;

/**
 * The Private Message service for the private message module.
 */
class PrivateMessageService implements PrivateMessageServiceInterface {

  /**
   * The private message mapper service.
   *
   * @var \Drupal\private_message\Mapper\PrivateMessageMapperInterface
   */
  protected PrivateMessageMapperInterface $mapper;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * Cache Tags Invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * The private message thread manager.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected EntityStorageInterface $pmThreadManager;

  /**
   * The user entity manager.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs a PrivateMessageService object.
   *
   * @param \Drupal\private_message\Mapper\PrivateMessageMapperInterface $mapper
   *   The private message mapper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    PrivateMessageMapperInterface $mapper,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    UserDataInterface $userData,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    EntityTypeManagerInterface $entityTypeManager,
    TimeInterface $time
  ) {
    $this->mapper = $mapper;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->userData = $userData;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->time = $time;
    $this->pmThreadManager = $entityTypeManager->getStorage('private_message_thread');
    $this->userManager = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadForMembers(array $members) {
    $thread_id = $this->mapper->getThreadIdForMembers($members);

    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
    }

    return $this->createPrivateMessageThread($members);
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadForUser(UserInterface $user) {
    $thread_id = $this->mapper->getFirstThreadIdForUser($user);
    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadsForUser($count, $timestamp = FALSE) {
    $return = [
      'threads' => [],
      'next_exists' => FALSE,
    ];

    $user = $this->userManager->load($this->currentUser->id());
    $thread_ids = $this->mapper->getThreadIdsForUser($user, $count, $timestamp);
    if (count($thread_ids)) {
      $threads = $this->pmThreadManager->loadMultiple($thread_ids);
      if (count($threads)) {
        $last_thread = end($threads);
        $last_timestamp = $last_thread->get('updated')->value;
        $return['next_exists'] = $this->mapper->checkForNextThread($user, $last_timestamp);
        $return['threads'] = $threads;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountThreadsForUser() {
    $user = $this->userManager->load($this->currentUser->id());
    $thread_ids = $this->mapper->getThreadIdsForUser($user);
    return count($thread_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getNewMessages($threadId, $messageId) {
    $response = [];

    $private_message_thread = $this->pmThreadManager->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      $messages = $private_message_thread->getMessages();
      $from_index = FALSE;
      foreach ($messages as $index => $message) {
        if ($message->id() > $messageId) {
          $from_index = $index;
          break;
        }
      }

      if ($from_index !== FALSE) {
        $response = array_splice($messages, $from_index);
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

    $private_message_thread = $this->pmThreadManager->load($threadId);
    if ($private_message_thread && $private_message_thread->isMember($this->currentUser->id())) {
      $user = $this->userManager->load($this->currentUser->id());
      $messages = $private_message_thread->filterUserDeletedMessages($user);
      $start_index = FALSE;
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

      if ($start_index !== FALSE) {
        $messages = array_splice($messages, $start_index, $slice_count);
        if (count($messages)) {
          $order = $settings['message_order'];
          if ($order == 'desc') {
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
  public function getUsersFromString($string, $count) {
    $user_ids = $this->mapper->getUserIdsFromString($string, $count);

    $accounts = [];
    if (count($user_ids)) {
      $accounts = $this->userManager->loadMultiple($user_ids);
    }

    return $accounts;
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedInboxThreads(array $existingThreadInfo, $count = FALSE) {
    $thread_info = $this->mapper->getUpdatedInboxThreadIds(array_keys($existingThreadInfo), $count);
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
      $new_threads = $this->pmThreadManager->loadMultiple($ids_to_load);
    }

    return [
      'thread_ids' => $thread_ids,
      'new_threads' => $new_threads,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validatePrivateMessageMemberUsername($username) {
    return $this->mapper->checkPrivateMessageMemberExists($username);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->mapper->getUnreadThreadCount($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadMessageCount() {
    $uid = $this->currentUser->id();
    $last_check_timestamp = $this->userData->get(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY);
    $last_check_timestamp = is_numeric($last_check_timestamp) ? $last_check_timestamp : 0;

    return (int) $this->mapper->getUnreadMessageCount($uid, $last_check_timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastCheckTime() {
    $uid = $this->currentUser->id();
    $this->userData->set(self::MODULE_KEY, $uid, self::LAST_CHECK_KEY, $this->time->getRequestTime());

    $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();

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
    $thread_id = $this->mapper->getThreadIdFromMessage($privateMessage);
    if ($thread_id) {
      return $this->pmThreadManager->load($thread_id);
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
      $current_user = \Drupal::currentUser();
      if ($current_user->isAuthenticated()) {
        if ($current_user->hasPermission('use private messaging system') && $current_user->id() != $author->id()) {
          $members = [$current_user, $author];
          $thread_id = $this->mapper->getThreadIdForMembers($members);
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
    return $this->mapper->getThreadIds();
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
    $thread = $this->pmThreadManager->create();
    foreach ($members as $member) {
      $thread->addMember($member);
    }

    $thread->save();

    return $thread;
  }

}
