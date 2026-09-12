<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\EventSubscriber;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features\Diff\Ckeditor5DiffInterface;
use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\ckeditor5_premium_features\Plugin\Filter\FilterCollaboration;
use Drupal\ckeditor5_premium_features\Utility\Collaborators;
use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\ckeditor5_premium_features\Utility\LibraryVersionChecker;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\FilterPluginManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Comment notification subscriber class.
 */
class NotificationDocumentUpdateSubscriber implements EventSubscriberInterface {

  use CKeditorPremiumLoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Collaboration filter.
   *
   * @var \Drupal\ckeditor5_premium_features\Plugin\Filter\FilterCollaboration
   */
  protected FilterCollaboration $filterCollaboration;

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSender $notificationSender
   *   Notification sender service.
   * @param \Drupal\ckeditor5_premium_features\Utility\Collaborators $collaboratorsService
   *   Collaborators service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   * @param \Drupal\ckeditor5_premium_features\Diff\Ckeditor5DiffInterface $ckeditor5Diff
   *   Ckeditor5 diff service.
   * @param \Drupal\ckeditor5_premium_features_notifications\Utility\NotificationSettings $notificationSettings
   *   Notifications settings helper.
   * @param \Drupal\filter\FilterPluginManager $filterPluginManager
   *   Filter plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    protected NotificationSender $notificationSender,
    protected Collaborators $collaboratorsService,
    protected AccountInterface $currentUser,
    protected Ckeditor5DiffInterface $ckeditor5Diff,
    protected NotificationSettings $notificationSettings,
    protected LibraryVersionChecker $libraryVersionChecker,
    FilterPluginManager $filterPluginManager
  ) {
    $this->filterCollaboration = $filterPluginManager->createInstance('ckeditor5_premium_features_collaboration_filter');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CollaborationEventBase::DOCUMENT_UPDATED => 'documentUpdated',
    ];
  }

  /**
   * Sends notifications.
   *
   * @param \Drupal\ckeditor5_premium_features\Event\CollaborationEventBase $event
   *   Suggestion event object.
   */
  public function documentUpdated(CollaborationEventBase $event): void {
    if (!ckeditor5_premium_features_check_htmldiff_installed()) {
      $message = $this->t("The content update notifications require a <code>caxy/php-htmldiff</code> library. The notifications weren't sent.");
      ckeditor5_premium_features_display_missing_dependency_warning($message);
      return;
    }
    $body = $event->getRelatedDocumentContent() ?? '';
    $previousBody = $event->getOriginalContent() ?? '';
    $relatedDocumentEntity = $event->getRelatedDocument();

    if (!$relatedDocumentEntity || $relatedDocumentEntity->isNew() || empty($previousBody) && empty($body)) {
      return;
    }

    $recipients = $event->getRelatedDocumentAuthors();

    if (empty($recipients)) {
      return;
    }

    if (!empty($body)) {
      $body = $this->filterDocument($body);
    }
    if (!empty($previousBody)) {
      $previousBody = $this->filterDocument($previousBody);
    }

    $this->ckeditor5Diff->getDiff($previousBody, $body);

    $changeContext = $this->ckeditor5Diff->getDiffContext();

    if (empty($changeContext)) {
      return;
    }

    $localEvent = clone $event;
    $localEvent->setOriginalContent($changeContext);

    try {
      $this->notificationSender->sendNotification(
        NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_DEFAULT,
        $recipients,
        $localEvent
      );
    }
    catch (PluginException $e) {
      $this->logException('An error occurred while sending Document Update notification', $e);
    }
  }

  /**
   * Filters unwanted collaboration content from the document.
   *
   * @param string $document
   *   Document to be processed.
   *
   * @return string
   *   Filtered document.
   */
  protected function filterDocument(string $document): string {
    $dom = Html::load($document);
    $xpath = new \DOMXPath($dom);

    if ($this->libraryVersionChecker->isLibraryVersionHigherOrEqual('40.1.0')) {
      $this->filterCollaboration->filterStyleSuggestion($xpath, $dom);
    }

    $this->filterCollaboration->filterComments($xpath);
    if ($this->notificationSettings->isMessageEnabled(NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED)) {
      $this->filterCollaboration->getHtmlHelper()->convertSuggestionsAttributes($dom, $xpath);
    }

    $dom->saveHTML();
    $htmlString = Html::serialize($dom);

    if ($this->notificationSettings->isMessageEnabled(NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_SUGGESTION_ADDED)) {
      $this->filterCollaboration->filterSuggestionsTags($htmlString);
    }

    return $htmlString;
  }

}
