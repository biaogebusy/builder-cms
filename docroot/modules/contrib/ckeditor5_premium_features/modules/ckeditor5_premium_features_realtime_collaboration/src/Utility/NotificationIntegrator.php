<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcNotificationEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Provides logic for notifications in rtc module.
 */
class NotificationIntegrator extends NotificationIntegratorBase {
  use MessengerTrait;

  /**
   * Dispatches document update event.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Source entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification Document helper.
   */
  public function handleDocumentUpdateEvent(FieldableEntityInterface $entity,
                                            NotificationDocumentHelper $documentHelper): void {
    $this->dispatchEvent($entity, CollaborationEventBase::DOCUMENT_UPDATED, $documentHelper);
  }

  /**
   * Prepare and send suggestions events.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param string $changeDate
   *   Last date of change.
   * @param array $suggestions
   *   Array of suggestions.
   * @param array $commentsThreads
   *   Array of comments threads.
   */
  public function handleSuggestionsEvent(FieldableEntityInterface $entity,
                                         NotificationDocumentHelper $documentHelper,
                                         string $changeDate,
                                         array $suggestions,
                                         array $commentsThreads): void {
    if (empty($suggestions)) {
      return;
    }
    $newSuggestions = array_filter($suggestions, function ($suggestion) use ($changeDate) {
      if (empty($suggestion['created_at'])) {
        return FALSE;
      }
      if (strtotime($suggestion['updated_at']) > $changeDate && $suggestion['state'] != 'open') {
        return TRUE;
      }
      return strtotime($suggestion['created_at']) > $changeDate;
    });
    foreach ($newSuggestions as $key => $suggestion) {
      // Do not process if suggestion is not attached to any item anymore,
      // unless it's a suggestion that has been resolved.
      $items = $suggestion['attributes']['items'] ?? [];
      $isResolved = $suggestion['state'] === 'accepted' || $suggestion['state'] === 'rejected';
      if (!$items && !$isResolved) {
        unset($newSuggestions[$key]);
        continue;
      }
      $newSuggestions[$key]['thread'] = $commentsThreads[$key] ?? [];
    }
    foreach ($newSuggestions as $suggestion) {
      $this->dispatchSuggestionEvent($suggestion, $entity, $documentHelper);
    }
  }

  /**
   * Overrides head attribute in case of grouped suggestions as they should be sent as a single notification.
   *
   * @param array $entities_data
   *   The entities data collected from markup.
   */
  public function processSuggestionGroups(&$entities_data): void {
    $groups = [];
    // Override head value of grouped suggestions, so they'll be sent in a single notification.
    foreach ($entities_data as $key => $element_data) {
      if (isset($element_data['attributes']['groupId'])) {
        $groupId = $element_data['attributes']['groupId'];
        if (!array_key_exists($element_data['attributes']['groupId'], $groups)) {
          $groups[$groupId] = $element_data['id'];
        }
        $entities_data[$key]['attributes']['head'] = $groups[$groupId];
      }
    }
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
  protected function dispatchSuggestionEvent(array $suggestion,
                                          FieldableEntityInterface $entity,
                                          NotificationDocumentHelper $documentHelper): void {
    $thread = [];
    $author = $this->loadAuthor($suggestion['author_id']);
    if (!empty($suggestion['thread']['comments'])) {
      foreach ($suggestion['thread']['comments'] as $comment) {
        $rtcComment = $this->createCommentEntity($comment, $author, $comment['createdAt']);
        $thread[$comment['commentId']] = $rtcComment;
      }
    }
    $rtcSuggestion = $this->createSuggestionEntity($entity, $suggestion, $thread, $author);
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
    $this->dispatchEvent($rtcSuggestion, $eventType, $documentHelper);
  }

  /**
   * Check if new comment has been added.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param string $changeDate
   *   Last date of change.
   * @param array $commentsThreads
   *   Array of comments threads.
   * @param array $suggestions
   *   Array of suggestions.
   */
  public function handleCommentsEvent(FieldableEntityInterface $entity,
                                      NotificationDocumentHelper $documentHelper,
                                      string $changeDate,
                                      array $commentsThreads,
                                      array $suggestions): void {
    if (empty($commentsThreads)) {
      return;
    }

    $newComments = [];
    foreach ($commentsThreads as $key => $commentThread) {
      if (empty($commentThread['comments'])) {
        continue;
      }
      foreach ($commentThread['comments'] as $comment) {
        if (strtotime($comment['createdAt']) > $changeDate) {
          if (!array_key_exists($key, $newComments)) {
            $newComments[$key] = $commentThread;
          }
          $newComments[$key]['new'][$comment['commentId']] = $comment;
          if (!array_key_exists('isReply', $newComments[$key])) {
            if (count($commentThread['comments']) > 1) {
              $newComments[$key]['isReply'] = TRUE;
            }
            else {
              $newComments[$key]['isReply'] = FALSE;
            }
          }
        }
      }
    }
    $this->dispatchCommentsEvent($newComments, $entity, $documentHelper, $suggestions);
  }

  /**
   * Prepare and dispatch comments event.
   *
   * @param array $newComments
   *   Array of new comments.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param array $suggestions
   *   Array of suggestions.
   */
  protected function dispatchCommentsEvent(array $newComments,
                                           FieldableEntityInterface $entity,
                                           NotificationDocumentHelper $documentHelper,
                                           array $suggestions) {
    foreach ($newComments as $key => $commentThread) {
      $thread = [];
      foreach ($commentThread['comments'] as $comment) {
        $author = $this->loadAuthor($comment['authorId']);
        $rtcComment = $this->createCommentEntity($comment, $author, $comment['createdAt']);
        $thread[$comment['commentId']] = $rtcComment;
      }
      $newComment = end($commentThread['new']);
      $rtcComment = $thread[$newComment['commentId']];
      $commentThread['isSuggestionComment'] = empty($commentThread['context']);

      $this->addThreadToCommentEntity($rtcComment, $thread, $key, $entity, $commentThread['isReply'] ?? FALSE);
      if ($commentThread['isSuggestionComment']) {
        $suggestion = $suggestions[$key] ?? NULL;
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
      $this->dispatchEvent($rtcComment, CollaborationEventBase::COMMENT_ADDED, $documentHelper);

      // Send mail to document author about new thread with comments.
      if (count($commentThread['comments']) > 1 && count($commentThread['new']) === count($commentThread['comments'])) {
        $rtcComment->setIsReply(FALSE);
        $this->dispatchEvent($rtcComment, CollaborationEventBase::COMMENT_ADDED, $documentHelper);
      }
    }
  }

  /**
   * Gather the data required for instant comment notification and pass to event dispatcher.
   *
   * @param array $data
   *   Comment related data retrieved from CKEditor plugin.
   */
  public function handleInstantCommentNotification(array $data): void {
    $comment = $data['comment'];
    $thread = [
      'id' => $data['thread_id'],
      'comments' => $data['thread']['comments']
    ];
    $channel = $this->entityTypeManager->getStorage('ckeditor5_channel')->load($data['channel_id']);
    $entity = $this->entityTypeManager->getStorage($channel->get('entity_type')->value)->loadByProperties(['uuid' => $channel->get('entity_id')->value]);
    $entity = reset($entity);

    $source_original_data = '';
    $documentHelper = new NotificationDocumentHelper($data['element_key'], $source_original_data, $data['editor_content']);

    $suggestionData = $this->apiAdapter->getDocumentSuggestions(
      $data['channel_id'], [
        'include_deleted' => 'true',
        'sort_by' => 'updated_at',
        'order' => 'desc',
      ]
    );
    $chainedSuggestions = $this->chainSuggestion($suggestionData);
    $suggestion = NULL;
    if (!$data['thread']['context']) {
      $suggestion = $chainedSuggestions[$data['thread_id']] ?? NULL;
      if (!$suggestion) {
        $this->messenger()->addWarning("One or more reply to a suggestion notification(s) couldn't be sent because suggestion data wasn't retrieved from cloud server.");
        return;
      }
    }

    $this->dispatchInstantCommentEvent($entity, $documentHelper, $comment, $thread, $suggestion);
  }

  /**
   * Prepare and dispatch instant comment event.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Related entity.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   * @param array $comment
   *   Added comment data.
   * @param array $thread
   *   Array of previous comments in thread.
   * @param array|NULL $suggestion
   *   Suggestion data. NULL in case comment is not related to a suggestion.
   */
  private function dispatchInstantCommentEvent(FieldableEntityInterface $entity,
    NotificationDocumentHelper $documentHelper,
    array $comment,
    array $thread,
    ?array $suggestion): void {

    $author = $this->loadAuthor($comment['authorId']);

    $threadEntities = [];
    foreach ($thread['comments'] as $threadComment) {
      $commentAuthor = $this->loadAuthor($threadComment['authorId']);
      $rtcThreadComment = $this->createCommentEntity($threadComment, $commentAuthor, $threadComment['createdAt']);
      $threadEntities[$threadComment['commentId']] = $rtcThreadComment;
    }
    $isReply = count($threadEntities) > 1;

    /** @var \Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity $rtcComment */
    $rtcComment = $this->createCommentEntity($comment, $author, $comment['createdAt']);

    $rtcComment->setThreadId($thread['id']);
    $rtcComment->setAuthor($author);
    $rtcComment->setThread($threadEntities);
    $rtcComment->setReferencedEntity($entity);
    $rtcComment->setIsReply($isReply);
    $rtcComment->setEntityTypeTargetId($entity->getEntityTypeId());

    if ($suggestion) {
      $suggestionAuthor = $this->loadAuthor($suggestion['author_id']);
      $rtcSuggestion = $this->createSuggestionEntity($entity, $suggestion, $threadEntities, $suggestionAuthor);
      $rtcComment
        ->setRelatedSuggestion($rtcSuggestion)
        ->setIsSuggestionComment(TRUE)
        ->setIsReply(TRUE);
    }
    $this->dispatchEvent($rtcComment, CollaborationEventBase::COMMENT_ADDED, $documentHelper);
  }

  /**
   * Dispatch event.
   *
   * @param \Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcNotificationEntityInterface|FieldableEntityInterface $entity
   *   Related entity.
   * @param string $eventType
   *   Event type.
   * @param NotificationDocumentHelper $documentHelper
   *   Notification document helper.
   */
  protected function dispatchEvent(RtcNotificationEntityInterface|FieldableEntityInterface $entity,
                                 string $eventType,
                                 NotificationDocumentHelper $documentHelper): void {
    $event = new CollaborationEventBase(
      $entity,
      $this->userStorage->load($this->currentUser->id()),
      $eventType,
    );
    $event->setRelatedDocumentKey($documentHelper->getElementId());
    if (!empty($documentHelper->getOriginalData())) {
      $event->setOriginalContent($documentHelper->getOriginalData());
    }
    if (!empty($documentHelper->getNewData())) {
      $event->setNewContent($documentHelper->getNewData());
    }

    $this->eventDispatcher->dispatch(
      $event,
      $eventType
    );
  }

}
