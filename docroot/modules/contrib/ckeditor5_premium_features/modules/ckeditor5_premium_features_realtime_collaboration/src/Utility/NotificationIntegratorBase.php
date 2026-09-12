<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Utility;

use Drupal\ckeditor5_premium_features\CKeditorDateFormatterTrait;
use Drupal\ckeditor5_premium_features\Utility\ApiAdapter;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcSuggestionNotificationEntity;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides logic for notifications in rtc module.
 */
abstract class NotificationIntegratorBase {

  use CKeditorDateFormatterTrait;

  /**
   * User storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $userStorage;

  /**
   * NotificationIntegrator constructor.
   *
   * @param \Drupal\ckeditor5_premium_features\Utility\ApiAdapter $apiAdapter
   *   Api adapter.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(protected ApiAdapter $apiAdapter,
                              protected AccountProxyInterface $currentUser,
                              protected EventDispatcherInterface $eventDispatcher,
                              protected EntityTypeManagerInterface $entityTypeManager) {
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * Create chained suggestions array.
   *
   * @param array $suggestions
   *   Suggestions to be chained.
   *
   * @return array
   *   Array of chained suggestions.
   */
  public function chainSuggestion(array $suggestions): array {
    $chainedSuggestions = [];
    foreach ($suggestions as $suggestion) {
      $head = $suggestion['attributes']['head'] ?? NULL;
      if ($head && $head !== $suggestion['id']) {
        NestedArray::setValue(
          $chainedSuggestions,
          [$head, 'chain', $suggestion['id']],
          $suggestion);
      }
      else {
        $chainedSuggestions[$suggestion['id']] = NestedArray::mergeDeep($suggestion, $chainedSuggestions[$suggestion['id']] ?? []);
        NestedArray::setValue($chainedSuggestions, [
          $suggestion['id'],
          'chain',
          $suggestion['id'],
        ],
          $suggestion);
      }
    }
    return $chainedSuggestions;
  }

  /**
   * Set key value as thread id.
   *
   * @param array $commentsData
   *   Array of comments.
   */
  public function transformCommentsData(array &$commentsData): void {
    foreach ($commentsData as $key => $comment) {
      $commentsData[$comment['threadId']] = $comment;
      unset($commentsData[$key]);
    }
  }

  /**
   * Creates comment entity.
   *
   * @param array $comment
   *   The comment data.
   * @param \Drupal\user\UserInterface|null $author
   *   Author of a comment.
   * @param string $createdAt
   *   The date of comment creation.
   *
   * @return \Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity
   *   The rtc comment notification entity.
   */
  protected function createCommentEntity(array $comment, ?UserInterface $author, string $createdAt): RtcCommentNotificationEntity {
    $rtcComment = new RtcCommentNotificationEntity();
    $rtcComment
      ->setId($comment['commentId'])
      ->setContent($comment['content'])
      ->setCreatedDate($this->format(strtotime($createdAt)))
      ->setAuthor($author);
    return $rtcComment;
  }

  /**
   * Adds thread to the comment entity.
   *
   * @param \Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity $commentEntity
   *   The comment entity to update.
   * @param array $thread
   *   The related thread.
   * @param string $threadId
   *   The tread id.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The related document entity.
   * @param bool $isReply
   *   Is comment a reply.
   */
  protected function addThreadToCommentEntity(RtcCommentNotificationEntity &$commentEntity, array $thread, string $threadId, FieldableEntityInterface $entity, bool $isReply): void {
    $commentEntity
      ->setIsReply($isReply)
      ->setThread($thread)
      ->setThreadId($threadId)
      ->setReferencedEntity($entity)
      ->setEntityTypeTargetId($entity->getEntityTypeId());
  }

  /**
   * Creates suggestion entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The related document entity.
   * @param array $suggestion
   *   The suggestion data.
   * @param array $thread
   *   The related thread.
   * @param \Drupal\user\UserInterface|null $author
   *   The author of a suggestion.
   *
   * @return \Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcSuggestionNotificationEntity
   *   The rtc suggestion notification entity.
   */
  protected function createSuggestionEntity(FieldableEntityInterface $entity, array $suggestion, array $thread, ?UserInterface $author): RtcSuggestionNotificationEntity {
    $rtcSuggestion = new RtcSuggestionNotificationEntity();
    $rtcSuggestion
      ->setId($suggestion['id'])
      ->setAuthor($author)
      ->setEntityTypeTargetId($entity->getEntityTypeId())
      ->setReferencedEntity($entity)
      ->setChain($suggestion['chain'] ?? [$suggestion['id'] => $suggestion])
      ->setThread($thread)
      ->setThreadId($suggestion['id']);
    return $rtcSuggestion;
  }

  /**
   * Loads user account with uuid.
   *
   * @param string $id
   *   User's uuid.
   *
   * @return \Drupal\user\UserInterface
   *   User account, or anonymous account if uuid is not found.
   */
  protected function loadAuthor(string $id): UserInterface {
    $author = $this->userStorage->loadByProperties(['uuid' => $id]);
    if ($author) {
      return reset($author);
    }

    // Cannot load valid account. Return anonymous user.
    return $this->userStorage->load(0);
  }

}
