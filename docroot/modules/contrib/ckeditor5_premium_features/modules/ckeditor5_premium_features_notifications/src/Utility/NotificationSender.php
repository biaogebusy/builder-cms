<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderPluginManager;
use Drupal\Core\Database\Connection;

/**
 * Class responsible for sending instant notifications.
 */
class NotificationSender {

  const NOTIFICATION_OPT_OUT_FIELD_TABLE = 'user__field_ck5_premium_notifications';
  const NOTIFICATION_OPT_OUT_FIELD_VALUE = 'field_ck5_premium_notifications_value';

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $dbConnection
   *   Database connection.
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings $notificationSettings
   *   Notification settings.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderPluginManager $senderPluginManager
   *   Notification sender plugin manager.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryPluginManager $messageFactoryPluginManager
   *   Notification message factory plugin manager.
   */
  public function __construct(protected Connection $dbConnection,
                              protected NotificationSettings $notificationSettings,
                              protected NotificationSenderPluginManager $senderPluginManager,
                              protected NotificationMessageFactoryPluginManager $messageFactoryPluginManager
  ) {}

  /**
   * Sends notification mail.
   *
   * @param string $messageType
   *   Message type.
   * @param array $recipientIds
   *   List of user IDs.
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Collaboration event.
   *
   * @return bool|array
   *   Sending status or info array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function sendNotification(string $messageType, array $recipientIds, CollaborationEventBase $event): bool|array {
    if (!$this->notificationSettings->isMessageEnabled($messageType)) {
      return FALSE;
    }

    $recipientIds = $this->filterRecipients($recipientIds);

    if (empty($recipientIds)) {
      return FALSE;
    }

    /** @var \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory */
    $messageFactory = $this->notificationSettings->getMessageFactoryPlugin();
    if (!$messageFactory) {
      return FALSE;
    }

    /** @var \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderInterface $sender */
    $sender = $this->getMessageSenderPlugin();
    if (!$sender) {
      return FALSE;
    }

    if ($messageType == NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_COMMENT ||
      $messageType == NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_DOCUMENT) {
      return $this->mentionsSender($sender, $messageFactory, $recipientIds, $event, $messageType);
    }
    else {
      return $this->basicSender($sender, $messageFactory, $recipientIds, $event, $messageType);
    }
  }

  /**
   * Internal method for executing notification sending for a mention event.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderInterface $sender
   *   Sender plugin.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   Message factory plugin.
   * @param array $recipientIds
   *   User IDs.
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Event entity.
   * @param string $messageType
   *   Type of message.
   */
  protected function mentionsSender(NotificationSenderInterface $sender,
                                    NotificationMessageFactoryInterface $messageFactory,
                                    array $recipientIds,
                                    CollaborationEventBase $event,
                                    string $messageType
  ): bool {
    foreach ($recipientIds as $userId) {
      $clonedEvent = clone $event;
      $clonedEvent->setReferencedUserId($userId);

      $this->basicSender($sender, $messageFactory, [$userId], $clonedEvent, $messageType);
    }

    return TRUE;
  }

  /**
   * Internal method for executing primary notification sending.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderInterface $sender
   *   Sender plugin.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   Message factory plugin.
   * @param array $recipientIds
   *   User IDs.
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Event entity.
   * @param string $messageType
   *   Type of message.
   */
  protected function basicSender(NotificationSenderInterface $sender,
                                 NotificationMessageFactoryInterface $messageFactory,
                                 array $recipientIds,
                                 CollaborationEventBase $event,
                                 string $messageType
  ): bool|array {
    $message = $messageFactory->getMessage($messageType, $event);
    if (empty($message)) {
      return FALSE;
    }

    return $sender->send($message, $recipientIds);
  }

  /**
   * Returns a list of user ids with notification consent.
   *
   * @param array $userIds
   *   List of user IDs.
   *
   * @return array
   *   List of user IDs.
   */
  protected function filterRecipients(array $userIds): array {
    if (empty($userIds)) {
      return [];
    }

    if (!$this->dbConnection->schema()->tableExists(self::NOTIFICATION_OPT_OUT_FIELD_TABLE)) {
      return $userIds;
    }

    return $this->dbConnection->select(self::NOTIFICATION_OPT_OUT_FIELD_TABLE, 'n')
      ->fields('n', ['entity_id'])
      ->condition('entity_id', $userIds, 'IN')
      ->condition(self::NOTIFICATION_OPT_OUT_FIELD_VALUE, 1)
      ->execute()
      ->fetchCol();
  }

  /**
   * Returns notification sender plugin instance.
   */
  protected function getMessageSenderPlugin(): NotificationSenderInterface|NULL {
    $pluginId = $this->notificationSettings->getSenderPluginId();
    if (!$this->senderPluginManager->hasDefinition($pluginId)) {
      return NULL;
    }

    return $this->senderPluginManager->createInstance($pluginId);
  }

}
