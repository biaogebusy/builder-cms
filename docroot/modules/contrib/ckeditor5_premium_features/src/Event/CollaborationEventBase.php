<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Event;

use Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface;
use Drupal\ckeditor5_premium_features_notifications\Utility\NotificationContextHelper;
use Drupal\ckeditor5_premium_features_realtime_collaboration\Entity\RtcNotificationEntityInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * Suggestion event class.
 */
class CollaborationEventBase extends Event {

  use TranslatorTrait;

  const DOCUMENT_UPDATED = 'ck5_collaboration_document_updated';
  const COMMENT_ADDED = 'ck5_collaboration_comment_added';
  const SUGGESTION_ACCEPT = 'ck5_collaboration_suggestion_accept';
  const SUGGESTION_DISCARD = 'ck5_collaboration_suggestion_discard';
  const SUGGESTION_ADDED = 'ck5_collaboration_suggestion_added';

  /**
   * Key property that describes the related field ID.
   *
   * @var string
   */
  protected string $relatedDocumentKey;

  /**
   * Original document content.
   *
   * @var string
   */
  protected string $originalContent;

  /**
   * New document content.
   *
   * @var string
   */
  protected string $newContent;

  /**
   * Optionally referenced user ID.
   *
   * @var string
   */
  protected string $referencedUserId;

  /**
   * Collaboration event constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $relatedEntity
   *   Event entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Event account.
   * @param string $eventType
   *   Type of event.
   */
  public function __construct(protected ContentEntityBase|RtcNotificationEntityInterface $relatedEntity,
                              protected AccountInterface $account,
                              protected string $eventType) {}

  /**
   * Returns event related entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|RtcNotificationEntityInterface
   *   Related entity.
   */
  public function getRelatedEntity():ContentEntityBase|RtcNotificationEntityInterface {
    return $this->relatedEntity;
  }

  /**
   * Sets related entity property.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase|RtcNotificationEntityInterface $relatedEntity
   *   Related entity.
   */
  public function setRelatedEntity(ContentEntityBase|RtcNotificationEntityInterface $relatedEntity): void {
    $this->relatedEntity = $relatedEntity;
  }

  /**
   * Returns related document. It can be the same as getRelatedEntity result.
   *
   * @return \Drupal\Core\Entity\ContentEntityBase|EntityInterface|RtcNotificationEntityInterface|FieldableEntityInterface|null
   *   Related document entity.
   */
  public function getRelatedDocument(): ContentEntityBase|EntityInterface|RtcNotificationEntityInterface|FieldableEntityInterface|null {
    $relatedEntity = $this->getRelatedEntity();

    if (!$relatedEntity instanceof CollaborationEntityInterface && !$relatedEntity instanceof RtcNotificationEntityInterface) {
      return $relatedEntity;
    }

    try {
      return $relatedEntity->getReferencedEntity();
    }
    catch (\Exception) {
    }

    return NULL;
  }

  /**
   * Returns "key" attribute from the related collaboration entity or NULL.
   *
   * @return string|null
   *   Related document field id.
   */
  public function getRelatedDocumentFieldId(): string|null {
    $relatedEntity = $this->getRelatedEntity();

    if (!$relatedEntity instanceof CollaborationEntityInterface) {
      return $this->relatedDocumentKey ?? NULL;
    }

    return $relatedEntity->getKey();
  }

  /**
   * Returns content of the proper field from related content entity.
   *
   * @return string|null
   *   Related document content.
   */
  public function getRelatedDocumentContent(): string|null {
    if ($this->getEventType() == self::SUGGESTION_DISCARD || $this->getEventType() == self::SUGGESTION_ACCEPT) {
      return $this->getOriginalContent();
    }

    if ($newContent = $this->getNewContent()) {
      return $newContent;
    }

    $relatedDocument = $this->getRelatedDocument();
    $fieldId = $this->relatedDocumentKey ?? $this->getRelatedDocumentFieldId();

    if (!$fieldId) {
      return NULL;
    }

    return NotificationContextHelper::getDocumentFieldContent($relatedDocument, $fieldId);
  }

  /**
   * Setter for the related document "key" property.
   *
   * @param string $key
   *   The document key.
   */
  public function setRelatedDocumentKey(string $key): void {
    $this->relatedDocumentKey = $key;
  }

  /**
   * Returns authors of the related content entity.
   *
   * @param bool $filterEventAuthor
   *   Flag if the current user should be filtered out of the list of users.
   */
  public function getRelatedDocumentAuthors(bool $filterEventAuthor = TRUE): array {
    $relatedDocument = $this->getRelatedDocument();

    $authors = [];
    if (method_exists($relatedDocument, 'getOwner')) {
      $authors[] = $relatedDocument->getOwner()->id();
    }
    elseif ($relatedDocument->hasField('uid')) {
      $authors[] = $relatedDocument->get('uid')->getString();
    }

    if ($filterEventAuthor) {
      $authors = array_diff($authors, [$this->getAccount()->id()]);
    }

    return $authors;
  }

  /**
   * Returns event account.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   Event account.
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Sets event account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   */
  public function setAccount(AccountInterface $account): void {
    $this->account = $account;
  }

  /**
   * Returns event type.
   *
   * @return string
   *   Event type.
   */
  public function getEventType(): string {
    return $this->eventType;
  }

  /**
   * Sets event type.
   *
   * @param string $eventType
   *   Type of the event.
   */
  public function setEventType(string $eventType): void {
    $this->eventType = $eventType;
  }

  /**
   * Returns label for specified event type.
   *
   * @param string $eventType
   *   Type of event.
   *
   * @throws \Exception
   *   Exception if type is not supported.
   */
  public static function getEventLabel(string $eventType): string|TranslatableMarkup {
    $supportedTypes = [
      self::SUGGESTION_ACCEPT => new TranslatableMarkup('accepted'),
      self::SUGGESTION_DISCARD => new TranslatableMarkup('rejected'),
    ];
    if (!isset($supportedTypes[$eventType])) {
      throw new \Exception('Unsupported event type');
    }

    return $supportedTypes[$eventType];
  }

  /**
   * Returns the original document content string.
   *
   * @return string|null
   *   The original content.
   */
  public function getOriginalContent(): ?string {
    return $this->originalContent ?? NULL;
  }

  /**
   * Sets the original document content string.
   *
   * @param string $documentContent
   *   String with original content.
   */
  public function setOriginalContent(string $documentContent) {
    $this->originalContent = $documentContent;
  }

  /**
   * Returns the new document content string.
   *
   * @return string|null
   *   The new content.
   */
  public function getNewContent(): ?string {
    return $this->newContent ?? NULL;
  }

  /**
   * Sets the new document content string.
   *
   * @param string $documentContent
   *   String with new content.
   */
  public function setNewContent(string $documentContent) {
    $this->newContent = $documentContent;
  }

  /**
   * Returns the referenced user ID property value.
   *
   * @return string|null
   *   The referenced user ID.
   */
  public function getReferencedUserId(): ?string {
    return $this->referencedUserId ?? NULL;
  }

  /**
   * Sets the referenced user ID property value.
   *
   * @param string $referencedUserId
   *   The referenced user ID.
   */
  public function setReferencedUserId(string $referencedUserId): void {
    $this->referencedUserId = $referencedUserId;
  }

}
