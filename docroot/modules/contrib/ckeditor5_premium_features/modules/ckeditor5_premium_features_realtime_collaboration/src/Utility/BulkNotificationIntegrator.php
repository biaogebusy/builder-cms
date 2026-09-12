<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features\Utility\ApiAdapter;
use Drupal\ckeditor5_premium_features_notifications\Entity\Message;
use Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationContextHelper;
use Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcSuggestionNotificationEntity;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides logic for bulk notifications in rtc module.
 */
class BulkNotificationIntegrator extends NotificationIntegratorBase {

  /**
   * Channel storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $channelStorage;

  /**
   * {@inheritDoc}
   */
  public function __construct(ApiAdapter $apiAdapter,
                              AccountProxyInterface $currentUser,
                              EventDispatcherInterface $eventDispatcher,
                              EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($apiAdapter, $currentUser, $eventDispatcher, $entityTypeManager);
    $this->channelStorage = $entityTypeManager->getStorage(ChannelInterface::ENTITY_TYPE_ID);

  }

  /**
   * Handle bulk notification.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Entity\Message $message
   *   The message entity.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   The message factory.
   *
   * @return array
   *   The notification body.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function handleBulkNotification(Message $message, NotificationMessageFactoryInterface $messageFactory): array {

    $body = [];
    $entityType = $message->get('entity_type')->value;
    $entityId = $message->get('entity_id')->value;

    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

    $messageItems = $message->getRelatedMessagesItems();
    $groupedMessageItems = $this->groupMessageItemsByKeyId($messageItems);
    $messageItemsTypeArr = $this->groupMessageItemsByEntityId($messageItems);

    foreach ($groupedMessageItems as $key => $keyMessageItem) {

      $this->processDocumentUpdate($body, $keyMessageItem, $messageFactory);

      $channel = $this->channelStorage->loadByProperties(
        [
          'entity_id' => $entity->uuid(),
          'key_id' => $key,
        ]
      );
      $channel = reset($channel);
      $documentData = NotificationContextHelper::getDocumentFieldContent($entity, $key);

      $commentsArr = [];
      $suggestionsArr = [];

      /**
       * @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface $messageItem
       */
      foreach ($keyMessageItem as $messageItem) {
        $messageEntityType = $messageItem->getRelatedEntityType();
        if ($messageEntityType === RtcSuggestionNotificationEntity::ENTITY_TYPE_ID) {
          $suggestionData = $this->apiAdapter->getSingleSuggestion($messageItem->getRelatedEntityId(), $channel->id(), [
            'include_deleted' => 'true',
          ]);
          if (empty($suggestionData)) {
            continue;
          }
          $content = $this->getProperMessageItemContent($messageItem);
          $suggestionData['document_content'] = $content;
          $suggestionData['event_type'] = $messageItem->getEventType();
          $suggestionsArr[$suggestionData['id']] = $suggestionData;
        }
        elseif ($messageEntityType === RtcCommentNotificationEntity::ENTITY_TYPE_ID) {
          $commentData = $this->apiAdapter->getSingleComment($messageItem->getRelatedEntityId(), $channel->id(), [
            'include_deleted' => 'true',
          ]);
          if (empty($commentData)) {
            continue;
          }
          $content = $this->getProperMessageItemContent($messageItem);
          $commentData['document_content'] = $content;
          $commentData['event_type'] = $messageItem->getEventType();
          $commentData['ref_uid'] = $messageItem->getRefUid();
          $commentsArr[$commentData['id']] = $commentData;
        }
      }

      $threadsArr = $this->prepareThreads($commentsArr, $channel);

      $documentHelper = new NotificationDocumentHelper($key, '', $documentData);
      $chainedSuggestions = $this->chainSuggestion($suggestionsArr);

      $suggestionEvents = $this->getSuggestionsEvent(
        $entity, $documentHelper, $chainedSuggestions, $threadsArr);
      $this->processSuggestionsEvents($body, $suggestionEvents, $messageFactory);

      $commentsEvents = $this->getCommentsEvent($entity, $documentHelper, $threadsArr);
      $this->processCommentsEvents($body, $commentsEvents, $messageFactory, $messageItemsTypeArr);
    }

    return $body;
  }

  /**
   * Adds suggestion events into notification body.
   *
   * @param array $body
   *   The notification body.
   * @param array $suggestionEvents
   *   Array of suggestion events.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   The message factory.
   */
  protected function processSuggestionsEvents(array &$body, array $suggestionEvents, NotificationMessageFactoryInterface $messageFactory):void {
    foreach ($suggestionEvents as $event) {
      $messageType = $event->getEventType() === CollaborationEventBase::SUGGESTION_ADDED ? NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED : NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_STATUS;
      $messageContent = $messageFactory->getMessage(
        $messageType,
        $event);
      $messageBodyArray = $messageContent->getMessageBody();
      $body[] = [
        '#theme' => 'notification_context',
        '#messageContent' => [
          '#markup' => implode('', $messageBodyArray),
          '#allowed_tags' => NotificationContextHelper::getNotificationAllowedTags(),
        ],
      ];
    }
  }

  /**
   * Adds comments events into notification body.
   *
   * @param array $body
   *   The notification body.
   * @param array $commentsEvents
   *   Array of comments events.
   * @param \Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface $messageFactory
   *   The message factory.
   * @param array $messageItemsTypeArr
   *   Array of grouped message items by entity type.
   */
  protected function processCommentsEvents(array &$body, array $commentsEvents, NotificationMessageFactoryInterface $messageFactory, array $messageItemsTypeArr): void {
    foreach ($commentsEvents as $event) {
      $messageType = NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_COMMENT_ADDED;
      if (isset($messageItemsTypeArr[$event->getRelatedEntity()->getId()])) {
        $messageType = $messageItemsTypeArr[$event->getRelatedEntity()->getId()];
      }

      $messageContent = $messageFactory->getMessage(
        $messageType,
        $event);
      $messageBodyArray = $messageContent->getMessageBody();
      $body[] = [
        '#theme' => 'notification_context',
        '#messageContent' => [
          '#markup' => implode('', $messageBodyArray),
          '#allowed_tags' => NotificationContextHelper::getNotificationAllowedTags(),
        ],
      ];
    }
  }

  /**
   * Adds document update event result into notification body.
   *
   * @param array $body
   *   The notification body.
   * @param array $keyMessageItem
   *   The message item.
   */
  protected function processDocumentUpdate(array &$body, array &$keyMessageItem, NotificationMessageFactoryInterface $messageFactory):void {
    $documentsUpdate = [];
    foreach ($keyMessageItem as $eventKey => $eventMessage) {
      if ($eventMessage->getEventType() === CollaborationEventBase::DOCUMENT_UPDATED) {
        $documentsUpdate[] = $eventMessage;
        unset($keyMessageItem[$eventKey]);
      }
    }
    foreach ($documentsUpdate as $documentUpdate) {
      $messageContent = $messageFactory->getMessage(
        $documentUpdate->getType(),
      $documentUpdate->getEvent()
      );

      $messageBodyArray = $messageContent->getMessageBody();
      $body[] = [
        '#theme' => 'notification_context',
        '#messageContent' => [
          '#markup' => implode('', $messageBodyArray),
          '#allowed_tags' => NotificationContextHelper::getNotificationAllowedTags(),
        ],
      ];
    }
  }

  /**
   * Groups comments into threads.
   *
   * @param array $commentsArr
   *   Array of comments.
   * @param \Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface $channel
   *   Channel id.
   *
   * @return array
   *   Grouped threads.
   */
  protected function prepareThreads(array $commentsArr, ChannelInterface $channel): array {
    $threadsArr = [];
    foreach ($commentsArr as $comment) {
      $threadId = $comment['thread_id'];
      $threadsArr[$threadId] = $comment['thread'];
      $threadComments = $this->apiAdapter->getDocumentComments($channel->id(), [
        'include_deleted' => 'false',
        'thread_id' => $threadId,
        'sort_by' => 'updated_at',
        'order' => 'asc',
      ]);
      foreach ($threadComments as $tKey => $threadComment) {
        $threadComments[$threadComment['id']] = $threadComment;
        if (!empty($commentsArr[$threadComment['id']]['document_content'])) {
          $threadComments[$threadComment['id']]['document_content'] = $commentsArr[$threadComment['id']]['document_content'];
        }
        if (!empty($commentsArr[$threadComment['id']]['ref_uid'])) {
          $threadComments[$threadComment['id']]['ref_uid'] = $commentsArr[$threadComment['id']]['ref_uid'];
        }
        unset($threadComments[$tKey]);
      }
      $threadsArr[$threadId]['comments'] = $threadComments;
    }
    return $threadsArr;
  }

  /**
   * Gets proper messageItem content for notification event.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface $messageItem
   *   The message item entity.
   *
   * @return string
   *   The content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getProperMessageItemContent(MessageItemInterface $messageItem): string {
    $messageItems = $this->entityTypeManager
      ->getStorage(MessageItemInterface::ENTITY_TYPE_ID)
      ->loadByProperties([
        'entity_id' => $messageItem->getRelatedEntityId(),
      ]);
    if (empty($messageItems)) {
      return '';
    }
    if (count($messageItems) > 1) {
      $content = '';
      $created = NULL;
      /**
       * @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface $item
       */
      foreach ($messageItems as $item) {
        if (!$created) {
          $created = $item->getCreatedTime();
          $content = $item->getMessageContent();
        }
        else {
          if ($created < $item->getCreatedTime()) {
            $created = $item->getCreatedTime();
            $content = $item->getMessageContent();
          }
        }
      }
      return $content;
    }
    return reset($messageItems)->getMessageContent();
  }

  /**
   * Group message items by the key id.
   *
   * @param array $messageItems
   *   Message items.
   *
   * @return array
   *   Grouped message items.
   */
  protected function groupMessageItemsByKeyId(array $messageItems):array {
    $groupedMessageItems = [];
    foreach ($messageItems as $item) {
      $groupedMessageItems[$item->getKeyId()][$item->getRelatedEntityId()] = $item;
    }
    return $groupedMessageItems;
  }

  /**
   * Group message items by the related entity id.
   *
   * @param array $messageItems
   *   Message items.
   *
   * @return array
   *   Grouped message items.
   */
  protected function groupMessageItemsByEntityId(array $messageItems): array {
    $messageItemsByEntityId = [];
    foreach ($messageItems as $item) {
      $messageItemsByEntityId[$item->getRelatedEntityId()] = $item->getType();
    }
    return $messageItemsByEntityId;
  }

  /**
   * Prepare comment events.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param array $commentsThreads
   *   Array of comments threads.
   *
   * @return array
   *   Array of comments events.
   */
  public function getCommentsEvent(FieldableEntityInterface $entity, NotificationDocumentHelper $documentHelper, array $commentsThreads): array {
    if (empty($commentsThreads)) {
      return [];
    }

    $newComments = [];
    foreach ($commentsThreads as $key => $commentThread) {
      if (empty($commentThread['comments'])) {
        continue;
      }
      foreach ($commentThread['comments'] as $comment) {
        $newComments[$key] = $commentThread;
        $newComments[$key]['new'][$comment['id']] = $comment;
        if (!array_key_exists('is_reply', $newComments[$key])) {
          if (count($commentThread['comments']) > 1) {
            $newComments[$key]['is_reply'] = TRUE;
          }
          else {
            $newComments[$key]['is_reply'] = FALSE;
          }
        }
      }
    }
    return $this->createCommentsEvents($newComments, $entity, $documentHelper);
  }

  /**
   * Prepare comments event object.
   *
   * @param array $threads
   *   Array of new comments.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   *
   * @return array
   *   Array of comments events.
   */
  protected function createCommentsEvents(array $threads, FieldableEntityInterface $entity, NotificationDocumentHelper $documentHelper): array {
    $commentEvents = [];
    $commentAuthor = NULL;
    foreach ($threads as $key => $commentThread) {
      $thread = [];
      foreach ($commentThread['comments'] as $comment) {
        $authorId = $comment['user']['id'] ?? 0;
        $commentAuthor = $this->loadAuthor($authorId);
        $comment['commentId'] = $comment['id'];
        $rtcComment = $this->createCommentEntity($comment, $commentAuthor, $comment['created_at']);
        $thread[$comment['id']] = $rtcComment;
      }
      $newComment = end($commentThread['new']);
      $rtcComment = $thread[$newComment['id']];
      $commentThread['isSuggestionComment'] = empty($commentThread['context']);

      $this->addThreadToCommentEntity($rtcComment, $thread, $key, $entity, $commentThread['is_reply'] ?? FALSE);

      if ($commentThread['isSuggestionComment']) {
        $suggestion = $this->apiAdapter->getSingleSuggestion($key, $commentThread['document_id'], [
          'include_deleted' => 'true',
        ]);
        if (empty($suggestion)) {
          continue;
        }

        $author = $this->loadAuthor($suggestion['author_id']);
        $rtcSuggestion = $this->createSuggestionEntity($entity, $suggestion, $thread, $author);

        $rtcComment
          ->setRelatedSuggestion($rtcSuggestion)
          ->setIsSuggestionComment($commentThread['isSuggestionComment'])
          ->setIsReply(TRUE);
      }
      $event = new CollaborationEventBase(
        $rtcComment,
        $commentAuthor,
        CollaborationEventBase::COMMENT_ADDED,
      );
      if (!empty($newComment['ref_uid'])) {
        $event->setReferencedUserId($newComment['ref_uid']);
      }
      $event->setRelatedDocumentKey($documentHelper->getElementId());
      if (!empty($commentThread['comments'][$rtcComment->getId()]['document_content'])) {
        $event->setOriginalContent($commentThread['comments'][$rtcComment->getId()]['document_content']);
      }
      if (!empty($documentHelper->getNewData())) {
        $event->setNewContent($documentHelper->getNewData());
      }
      $commentEvents[] = $event;
    }
    return $commentEvents;
  }

  /**
   * Prepare suggestion events.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param array $suggestions
   *   Array of suggestions.
   * @param array $commentsThreads
   *   Array of comments threads.
   */
  public function getSuggestionsEvent(FieldableEntityInterface $entity,
                                         NotificationDocumentHelper $documentHelper,
                                         array $suggestions,
                                         array $commentsThreads): array {
    $events = [];
    if (empty($suggestions)) {
      return $events;
    }
    $newSuggestions = $suggestions;
    foreach ($newSuggestions as $key => $suggestion) {
      $newSuggestions[$key]['thread'] = $commentsThreads[$key] ?? [];
    }
    foreach ($newSuggestions as $key => $suggestion) {
      $suggestion['chain'] = [$key => $suggestion];
      $event = $this->createSuggestionEvent($suggestion, $entity, $documentHelper);
      $events[] = $event;
    }
    return $events;
  }

  /**
   * Prepare suggestion event object.
   *
   * @param array $suggestion
   *   The suggestion.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   */
  protected function createSuggestionEvent(array $suggestion,
                                          FieldableEntityInterface $entity,
                                          NotificationDocumentHelper $documentHelper): CollaborationEventBase {
    $thread = [];
    $author = $this->loadAuthor($suggestion['author_id']);
    if (!empty($suggestion['thread']['comments'])) {
      foreach ($suggestion['thread']['comments'] as $comment) {
        $comment['commentId'] = $comment['id'];
        $rtcComment = $this->createCommentEntity($comment, $author, $comment['created_at']);
        $thread[$comment['commentId']] = $rtcComment;
      }
    }
    $rtcSuggestion = $this->createSuggestionEntity($entity, $suggestion, $thread, $author);
    if (!$suggestion['event_type']) {
      switch ($suggestion['state']) {
        case 'accepted':
          $eventType = CollaborationEventBase::SUGGESTION_ACCEPT;
          break;

        case 'rejected':
          $eventType = CollaborationEventBase::SUGGESTION_DISCARD;
          break;

        default:
          $eventType = CollaborationEventBase::SUGGESTION_ADDED;
          break;
      }
    }
    else {
      $eventType = $suggestion['event_type'];
    }

    $event = new CollaborationEventBase(
      $rtcSuggestion,
      $author,
      $eventType,
    );
    $event->setRelatedDocumentKey($documentHelper->getElementId());
    if (!empty($suggestion['document_content'])) {
      $event->setOriginalContent($suggestion['document_content']);
    }
    if (!empty($documentHelper->getNewData())) {
      $event->setNewContent($documentHelper->getNewData());
    }
    return $event;
  }

}
