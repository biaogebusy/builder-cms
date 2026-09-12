<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\ckeditor5_premium_features_notifications\Entity\MessageInterface;
use Drupal\ckeditor5_premium_features_notifications\Entity\MessageStorage;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationSenderMailBulk;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

/**
 * Class responsible for preparing and sending bulk messages.
 */
class BulkMessageSender {

  use StringTranslationTrait;

  /**
   * The message storage.
   *
   * @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageStorage
   */
  protected MessageStorage $messageStorage;

  /**
   * The Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param NotificationSettings $notificationSettings
   *   The notification settings service.
   * @param BulkMessageBodyHandlerManager $bulkMessageBodyHandlerManager
   *   The message body handler manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager,
                              protected MailManagerInterface $mailManager,
                              protected RendererInterface $renderer,
                              protected NotificationSettings $notificationSettings,
                              protected BulkMessageBodyHandlerManager $bulkMessageBodyHandlerManager
  ) {
    $this->messageStorage = $this->entityTypeManager->getStorage(MessageInterface::ENTITY_TYPE_ID);
  }

  /**
   * Prepares mail content.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Entity\Message $message
   *   Message entity.
   *
   * @return string
   *   Rendered body of message.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function prepareContent(Message $message): string {
    /** @var \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory */
    $messageFactory = $this->notificationSettings->getMessageFactoryPlugin();
    $body = $this->bulkMessageBodyHandlerManager->getHandler()->prepareBody($message, $messageFactory);
    $messageOuterWrapper = [
      '#theme' => 'notification_message_bulk',
      '#title' => $this->t('Document "@title" recent activities', [
        '@title' => $message->getTitle(),
      ]),
      '#items' => $body,
    ];

    return $this->renderer->renderInIsolation($messageOuterWrapper)->__toString();
  }

  /**
   * Callback from cron job.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function sendBulkMails(): void {
    $messages = $this->messageStorage->getOldestMessages(
      10,
      $this->notificationSettings->getBulkNotificationsInterval()
    );

    foreach ($messages as $message) {
      $user = $message->getUser();
      $body = $this->prepareContent($message);

      if (!empty($body)) {
        $this->sendMail($message->getTitle(), [$body], $user);
      }

      $message->set('sent', 1);
      $message->save();
    }
    foreach ($messages as $message) {
      $this->messageStorage->cleanMessageItems($message);
    }
  }

  /**
   * Sends mail.
   *
   * @param string $title
   *   Title of message.
   * @param array $body
   *   Body of message.
   * @param \Drupal\user\Entity\User $user
   *   User entity.
   */
  private function sendMail(string $title, array $body, User $user): void {
    $params["subject"] = $title;
    $params["body"] = $body;
    $userMail = $user->getEmail();
    if (!$userMail) {
      return;
    }
    $this->mailManager->mail(
      "ckeditor5_premium_features_notifications",
      NotificationSenderMailBulk::BULK_MAIL_TYPE,
      $userMail,
      NULL,
      $params
    );
  }

}
