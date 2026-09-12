<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Utility;

use Drupal\ckeditor5_premium_features\Utility\BulkMessageBodyHandlerInterface;
use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;

/**
 * Class responsible for preparing body for bulk message.
 */
class DefaultBulkMessageBodyHandler implements BulkMessageBodyHandlerInterface {

  /**
   * {@inheritDoc}
   */
  public function prepareBody(Message $message, NotificationMessageFactoryInterface $messageFactory): array {
    $messageItems = $message->getItems();
    $body = [];

    foreach ($messageItems as $messageItem) {
      /** @var \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageInterface $messageContent */
      $messageContent = $messageFactory->getMessage($messageItem->getType(), $messageItem->getEvent());
      $messageBodyArray = $messageContent->getMessageBody();

      $body[$messageItem->id()] = [
        '#theme' => 'notification_context',
        '#messageContent' => [
          '#markup' => implode('', $messageBodyArray),
          '#allowed_tags' => NotificationContextHelper::getNotificationAllowedTags(),
        ],
      ];

    }
    return $body;
  }

}
