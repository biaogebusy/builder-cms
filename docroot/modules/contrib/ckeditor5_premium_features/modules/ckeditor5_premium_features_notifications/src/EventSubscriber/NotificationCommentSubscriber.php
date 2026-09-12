<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\EventSubscriber;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features\Utility\Collaborators;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\Comment;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcCommentNotificationEntity;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcNotificationEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Comment notification subscriber class.
 */
class NotificationCommentSubscriber implements EventSubscriberInterface {

  /**
   * Event subscriber constructor.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender $notificationSender
   *   Notification sender service.
   * @param \Drupal\ckeditor5_premium_features\Utility\Collaborators $collaboratorsService
   *   Collaborators utility service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   */
  public function __construct(
    protected NotificationSender $notificationSender,
    protected Collaborators $collaboratorsService,
    protected AccountInterface $currentUser,
    protected StateInterface $state
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollaborationEventBase::COMMENT_ADDED => 'commentAdded',
    ];
  }

  /**
   * Sends notifications.
   *
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Suggestion event object.
   */
  public function commentAdded(CollaborationEventBase $event): void {
    $collaborationEntity = $event->getRelatedEntity();
    if (!$collaborationEntity instanceof Comment && !$collaborationEntity instanceof RtcCommentNotificationEntity) {
      return;
    }

    $newSuggestionParticipators = $this->getSuggestionNotificationUsers($collaborationEntity);

    $participators = $this->collaboratorsService->getParticipators($collaborationEntity);
    $threadSuggestionAuthor = $this->collaboratorsService->getThreadSuggestionAuthor($collaborationEntity);
    if ($threadSuggestionAuthor) {
      $participators[] = $threadSuggestionAuthor;
    }
    $isSuggestionReply = $this->collaboratorsService->isCommentInSuggestionThread($collaborationEntity);
    $participators = array_unique($participators);
    $replyRecipients = [];
    $authors = $event->getRelatedDocumentAuthors();

    $participators = array_diff($participators, $newSuggestionParticipators);

    if (!$collaborationEntity->isReply()) {
      // If it's not a reply, notify only the document authors.
      $replyRecipients = $authors;

      if (!empty($replyRecipients)) {
        // Send notification to the document author.
        $this->notificationSender->sendNotification(
          NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_COMMENT_ADDED,
          $replyRecipients,
          $event
        );
      }
    }

    if (!empty($participators) && empty($replyRecipients)) {
      if (!$isSuggestionReply) {
        // Send notification to users participated in a thread.
        $this->notificationSender->sendNotification(
          NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_THREAD_REPLY,
          $participators,
          $event
        );
      }
      elseif ($this->collaboratorsService->isSuggestionExists($collaborationEntity)) {
        // Send notification to the suggestion author.
        $this->notificationSender->sendNotification(
            NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_REPLY,
            $participators,
            $event
          );
      }
    }

    $mentions = $this->collaboratorsService->getCommentMentions($collaborationEntity);
    if (!empty($mentions)) {
      $users = $this->collaboratorsService->getUserIdsByNames($mentions);

      if ($isSuggestionReply) {
        $users = array_diff($users, $newSuggestionParticipators);
      }

      $this->checkIfNotificationAlreadySentToUsers(
        $users,
        array_merge($replyRecipients, $participators)
      );
      if (!empty($users)) {
        $this->notificationSender->sendNotification(
          NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_COMMENT,
          $users,
          $event
        );
      }
    }
  }

  /**
   * Check if notification was sent to user.
   *
   * Don't send a notification about a mention to the user
   * if received a notification about the comment
   * where the mention is placed.
   *
   * @param array $users
   *   Array with mentioned users.
   * @param array $recipients
   *   Array with users who already have received notification.
   */
  protected function checkIfNotificationAlreadySentToUsers(array &$users, array $recipients): void {
    if (!empty($recipients)) {
      $users = array_filter($users, fn($x) => !in_array($x, $recipients));
    }
  }

  /**
   * Get users notified about suggestion.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $collaborationEntity
   *   Collaboration entity.
   *
   * @return array
   *   The array of users to be excluded in comment notification
   */
  protected function getSuggestionNotificationUsers(CollaborationEntityInterface|RtcNotificationEntityInterface $collaborationEntity): array {
    $notificationSentToUsers = $this->state->get(NotificationMessageFactoryInterface::CKEDITOR5_SUGGESTION_SENT_TO_USERS_STATE_KEY);
    $threadId = $collaborationEntity->getThreadId();
    $newSuggestionParticipators = $notificationSentToUsers[$threadId] ?? [];

    if (!empty($newSuggestionParticipators)) {
      $this->removeUsersFromStateArray($notificationSentToUsers, $threadId);
    }

    return $newSuggestionParticipators;
  }

  /**
   * Remove Users from the state array that collects users already notified about the suggestion.
   *
   * @param array $notificationSentToUsers
   *   Array of users notified about suggestion.
   * @param string $threadId
   *   Thread id.
   *
   * @return void
   */
  protected function removeUsersFromStateArray(array $notificationSentToUsers, string $threadId): void {
    unset($notificationSentToUsers[$threadId]);
    $this->state->set(NotificationMessageFactoryInterface::CKEDITOR5_SUGGESTION_SENT_TO_USERS_STATE_KEY, $notificationSentToUsers);
  }

}
