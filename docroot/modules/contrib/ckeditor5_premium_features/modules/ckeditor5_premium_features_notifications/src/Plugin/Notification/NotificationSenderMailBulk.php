<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Plugin\Notification;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for sending notifications through mail.
 */
class NotificationSenderMailBulk extends NotificationSenderBase implements ContainerFactoryPluginInterface {

  use CKeditorPremiumLoggerChannelTrait;

  const BULK_MAIL_TYPE = 'ckeditor5_premium_features_notifications_bulk';

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationMessageInterface $message, array $userIds): bool {
    $documentId = $message->getSourceEvent()->getRelatedDocument()->id();
    $documentType = $message->getSourceEvent()->getRelatedDocument()->getEntityTypeId();

    $documentContent = $message->getSourceEvent()->getRelatedDocumentContent();
    $originalContent = $message->getSourceEvent()->getOriginalContent();

    try {

      /** @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageStorage $messageQueueStorage */
      $messageQueueStorage = $this->entityTypeManager->getStorage(Message::ENTITY_TYPE_ID);

      foreach ($userIds as $userId) {
        /** @var \Drupal\ckeditor5_premium_features_notifications\Entity\Message $messageQueueEntity */
        $messageQueueEntity = $messageQueueStorage->getMessageForUserAndDocument($userId, $documentId, $documentType);
        if (!$messageQueueEntity) {
          $messageQueueEntity = $messageQueueStorage->createMessage($userId, $documentId, $documentType);

          if (!$messageQueueEntity) {
            continue;
          }
          $messageQueueEntity->save();
        }
        $messageQueueEntity->appendItem(
          $message->getSourceEvent()->getRelatedEntity()->getEntityTypeId(),
          $message->getSourceEvent()->getRelatedEntity()->id(),
          $message->getType(),
          $message->getSourceEvent()->getEventType(),
          empty($originalContent) ? $documentContent : $originalContent,
          strval($message->getSourceEvent()->getAccount()->id()),
          $message->getSourceEvent()->getRelatedDocumentFieldId(),
          $message->getSourceEvent()->getReferencedUserId()
        );
      }

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logException('Suggestion notification sending error', $e);
    }

    return FALSE;
  }

}
