<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_notifications\EventSubscriber;

use Drupal\ckeditor5_premium_features_collaboration\Entity\Suggestion;
use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcSuggestionNotificationEntity;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Suggestion notification subscriber class.
 */
class NotificationSuggestionSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender $notificationSender
   *   Notification sender service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user object.
   */
  public function __construct(
    protected NotificationSender $notificationSender,
    protected AccountInterface $currentUser,
    protected StateInterface $state
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollaborationEventBase::SUGGESTION_ACCEPT => 'suggestionStatusChange',
      CollaborationEventBase::SUGGESTION_DISCARD => 'suggestionStatusChange',
      CollaborationEventBase::SUGGESTION_ADDED => 'suggestionAdd',
    ];
  }

  /**
   * Sends notifications.
   *
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Suggestion event object.
   */
  public function suggestionStatusChange(CollaborationEventBase $event): void {
    $collaborationEntity = $event->getRelatedEntity();
    if (!$collaborationEntity instanceof Suggestion && !$collaborationEntity instanceof RtcSuggestionNotificationEntity) {
      return;
    }

    if ($collaborationEntity->getAuthorId() == $event->getAccount()->id()) {
      return;
    }

    if ($collaborationEntity->isInChain() && !$collaborationEntity->isHeadOfChain()) {
      return;
    }

    $recipients = [
      $collaborationEntity->getAuthorId(),
    ];

    $this->notificationSender->sendNotification(
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_STATUS,
      $recipients,
      $event
    );
  }

  /**
   * Sends notifications.
   *
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Suggestion event object.
   */
  public function suggestionAdd(CollaborationEventBase $event): void {
    $collaborationEntity = $event->getRelatedDocument();
    if (!$collaborationEntity) {
      return;
    }

    $recipients = $event->getRelatedDocumentAuthors();

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Suggestion $suggestion */
    $suggestion = $event->getRelatedEntity();
    $suggestionAuthor = $suggestion->getAuthorId();

    // There are cases, when an existing suggestion is split by another user.
    if ($suggestionAuthor != $this->currentUser->id()) {
      return;
    }

    $recipients = array_diff($recipients, [$suggestionAuthor]);

    if (empty($recipients)) {
      return;
    }

    $suggestionId = $suggestion->getId();
    $authors = $this->state->get(NotificationMessageFactoryInterface::CKEDITOR5_SUGGESTION_SENT_TO_USERS_STATE_KEY) ?? [];
    $authors[$suggestionId] = $recipients;
    $this->state->set(NotificationMessageFactoryInterface::CKEDITOR5_SUGGESTION_SENT_TO_USERS_STATE_KEY, $authors);

    $this->notificationSender->sendNotification(
      NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED,
      $recipients,
      $event
    );
  }

}
