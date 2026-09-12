<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\Comment;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage;
use Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Class for collecting collaborators data.
 */
class Collaborators {

  /**
   * The "suggestion" storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage
   */
  protected SuggestionStorage $suggestionStorage;

  /**
   * The "comments" storage.
   *
   * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage
   */
  protected CommentsStorage $commentsStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param MentionsIntegrator $mentionsIntegrator
   *   Mentions integrator service.
   * @param CollaborationModuleIntegrator $collaborationModuleIntegrator
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(protected Connection $connection,
                              protected EntityTypeManagerInterface $entityTypeManager,
                              protected MentionsIntegrator $mentionsIntegrator,
                              protected CollaborationModuleIntegrator $collaborationModuleIntegrator
  ) {
    if ($collaborationModuleIntegrator->isNonRtcEnabled()) {
      $this->commentsStorage = $this->entityTypeManager->getStorage(CommentInterface::ENTITY_TYPE_ID);
      $this->suggestionStorage = $this->entityTypeManager->getStorage(SuggestionInterface::ENTITY_TYPE_ID);
    }
  }

  /**
   * Returns a list of user ids that collaborated on specified entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to get collaborators for.
   * @param int $userIdExclude
   *   ID of a user that should be excluded from the list of collaborators.
   *
   * @return array
   *   A list of collaborators id.
   */
  public function getCollaborators(FieldableEntityInterface $entity, int $userIdExclude = 0): array {
    $suggestionAuthors = $this->getEntityCollaboratorType(SuggestionInterface::ENTITY_TYPE_ID, $entity->uuid(), $entity->getEntityTypeId(), $userIdExclude);
    $revisionAuthors = $this->getEntityCollaboratorType(RevisionInterface::ENTITY_TYPE_ID, $entity->uuid(), $entity->getEntityTypeId(), $userIdExclude);
    $commentAuthors = $this->getEntityCollaboratorType(CommentInterface::ENTITY_TYPE_ID, $entity->uuid(), $entity->getEntityTypeId(), $userIdExclude);

    if (empty($suggestionAuthors) && empty($revisionAuthors) && empty($commentAuthors)) {
      return [];
    }

    return array_unique(array_merge($suggestionAuthors, $revisionAuthors, $commentAuthors));
  }

  /**
   * Returns comment thread participators IDs.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $comment
   *   Comment to be checked.
   */
  public function getParticipators(CollaborationEntityInterface|RtcCommentNotificationEntity $comment): array {
    if ($comment instanceof RtcCommentNotificationEntity) {
      $commentsInThread = $comment->getThread();
    }
    else {
      $commentsInThread = $this->getCommentsThread($comment->getThreadId());
    }

    if (empty($commentsInThread)) {
      return [];
    }

    $collaboratorIds = [];
    $mentionedUsers = [];

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $threadComment */
    foreach ($commentsInThread as $threadComment) {
      if ($threadComment->id() == $comment->id()) {
        continue;
      }
      if ($mentionedInComment = $this->getCommentMentions($threadComment)) {
        $mentionedUsers = array_merge($mentionedUsers, $mentionedInComment);
      }
      if ($threadComment->getAuthorId() == $comment->getAuthorId()) {
        continue;
      }

      $collaboratorIds[] = $threadComment->getAuthorId();
    }

    if (!empty($mentionedUsers)) {
      $collaboratorIds = array_merge($collaboratorIds, $this->getUserIdsByNames($mentionedUsers));
    }

    $collaboratorIds = array_diff($collaboratorIds, [$comment->getAuthorId()]);

    return array_unique($collaboratorIds);
  }

  /**
   * Returns suggestion author ID if comment is a suggestion reply.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\Comment $comment
   *   Comment to be checked.
   *
   * @return int|null
   *   Returns author ID or NULL if no suggestion matches the comment.
   */
  public function getThreadSuggestionAuthor(Comment|RtcCommentNotificationEntity $comment): int|NULL {
    if (!$this->isCommentInSuggestionThread($comment)) {
      return NULL;
    }

    if ($comment instanceof RtcCommentNotificationEntity) {
      $suggestionAuthorId = $comment->getRelatedSuggestionAuthorId();
      if ($suggestionAuthorId != $comment->getAuthorId()) {
        return $suggestionAuthorId;
      }
      return NULL;
    }

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $suggestion */
    try {
      $suggestion = $this->suggestionStorage->load($comment->getThreadId());

      if ($suggestion->getAuthorId() != $comment->getAuthorId()) {
        return $suggestion->getAuthorId();
      }
    }
    catch (\Exception $e) {
    }

    return NULL;
  }

  /**
   * Returns a list of comments matched by the thread ID.
   *
   * @param string $threadId
   *   ID of th thread.
   */
  protected function getCommentsThread(string $threadId): array {
    try {
      return $this->commentsStorage->loadByProperties([
        'thread_id' => $threadId,
      ]);
    }
    catch (\Exception $e) {
    }

    return [];
  }

  /**
   * Returns author ids for specified collaboration entity type.
   *
   * @param string $collaborationEntityType
   *   Collaboration entity type.
   * @param string $entityId
   *   Referenced entity.
   * @param string $entityTypeId
   *   Referenced entity type id.
   * @param int $userIdExclude
   *   Author id to be excluded from results.
   *
   * @return array
   *   A list of author ids.
   */
  protected function getEntityCollaboratorType(string $collaborationEntityType, string $entityId, string $entityTypeId, int $userIdExclude = 0): array {
    $query = $this->connection->select($collaborationEntityType, 'd')
      ->fields('d', ['uid'])
      ->condition('entity_id', $entityId)
      ->condition('entity_type', $entityTypeId);

    if ($userIdExclude > 0) {
      $query->condition('uid', $userIdExclude, '!=');
    }

    return $query->execute()->fetchCol();
  }

  /**
   * Checks if passed thread is a thread for a suggestion entity.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface[] $comments
   *   Comments thread to be verified.
   */
  protected function isSuggestionThread(array $comments): bool {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Comment $threadComment */
    foreach ($comments as $threadComment) {
      if ($threadComment->getPosition() == 0 && $threadComment->isReply()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns a list of users names mentioned in the comment.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface $comment
   *   Comment object.
   */
  public function getCommentMentions(CommentInterface|RtcCommentNotificationEntity $comment): array {
    if (!$this->mentionsIntegrator->isMentionInstalled()) {
      return [];
    }
    $mentionsHelper = $this->mentionsIntegrator->getMentionHelperService();
    $commentBody = $comment->getContent();

    return $mentionsHelper->getMentions($commentBody);
  }

  /**
   * Returns a list of IDs for specified usernames.
   *
   * @param array $userNames
   *   List of usernames.
   */
  public function getUserIdsByNames(array $userNames): array {
    try {
      return $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('name', $userNames, 'IN')
        ->execute();
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Check if the comment is placed in a suggestion thread.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\Comment $comment
   *   Comment entity.
   *
   * @return bool
   *   Return true if the comment is in the suggestion tread.
   */
  public function isCommentInSuggestionThread(Comment|RtcCommentNotificationEntity $comment): bool {
    if ($comment instanceof RtcCommentNotificationEntity) {
      return $comment->isSuggestionComment();
    }
    // Get thread.
    $commentsInThread = $this->getCommentsThread($comment->getThreadId());

    if (!$this->isSuggestionThread($commentsInThread)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Check if suggestion exists for the passed comment.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\Comment $comment
   *   Comment entity.
   *
   * @return bool
   *   Return true if suggestion exists and is not discarded or accepted.
   */
  public function isSuggestionExists(Comment|RtcCommentNotificationEntity $comment): bool {
    if ($comment instanceof RtcCommentNotificationEntity) {
      return $comment->isSuggestionComment();
    }
    /**
     * @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Suggestion $suggestion
     */
    $suggestion = $this->suggestionStorage->load($comment->getThreadId());
    if (!$suggestion) {
      return FALSE;
    }
    $suggestionStatus = $suggestion->getStatus();
    if ($suggestionStatus === 'discard' || $suggestionStatus === 'accept') {
      return FALSE;
    }
    return TRUE;
  }

}
