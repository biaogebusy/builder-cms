<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Entity;

/**
 * Helper comment entity for dealing with notifications in rtc module.
 */
class RtcCommentNotificationEntity extends RtcNotificationEntityBase {

  public const ENTITY_TYPE_ID = 'ckeditor5_comment';

  /**
   * The content.
   *
   * @var string
   */
  private string $content;

  /**
   * Is reply.
   *
   * @var bool
   */
  private bool $isReply;

  /**
   * Is suggestion comment.
   *
   * @var bool
   */
  private bool $isSuggestionComment = FALSE;

  /**
   * Related suggestion.
   *
   * @var RtcSuggestionNotificationEntity|null
   */
  private ?RtcSuggestionNotificationEntity $relatedSuggestion = NULL;

  /**
   * Created date.
   *
   * @var string
   */
  private string $createdDate;

  /**
   * {@inheritDoc}
   */
  public function getEntityTypeId(): string {
    return self::ENTITY_TYPE_ID;
  }

  /**
   * Sets content.
   *
   * @param string $content
   *   The current object.
   */
  public function setContent(string $content): static {
    $this->content = $content;
    return $this;
  }

  /**
   * Returns content.
   *
   * @return string
   *   The content.
   */
  public function getContent(): string {
    return $this->content;
  }

  /**
   * Sets created date.
   *
   * @param string $createdDate
   *   The current object.
   */
  public function setCreatedDate(string $createdDate): static {
    $this->createdDate = $createdDate;
    return $this;
  }

  /**
   * Returns created date.
   *
   * @return string
   *   Created date.
   */
  public function getCreatedDate():string {
    return $this->createdDate;
  }

  /**
   * Is comment reply.
   *
   * @return bool
   *   True if comment is reply.
   */
  public function isReply():bool {
    return $this->isReply;
  }

  /**
   * Sets value for is reply field.
   *
   * @param bool $isReply
   *   True or false.
   *
   * @return RtcCommentNotificationEntity
   *   The current object.
   */
  public function setIsReply(bool $isReply):static {
    $this->isReply = $isReply;
    return $this;
  }

  /**
   * Returns content without HTML markup.
   *
   * @return string|null
   *   The content.
   */
  public function getContentPlain(): string|null {
    $content = $this->getContent();
    if (empty($content)) {
      return NULL;
    }

    return str_replace(chr(0xC2) . chr(0xA0), ' ', html_entity_decode(strip_tags($content)));
  }

  /**
   * Sets is suggestion comment value.
   *
   * @param bool $isSuggestionComment
   *   True or false.
   *
   * @return RtcCommentNotificationEntity
   *   The current object.
   */
  public function setIsSuggestionComment(bool $isSuggestionComment): static {
    $this->isSuggestionComment = $isSuggestionComment;
    return $this;
  }

  /**
   * Is comment is in a suggestion.
   *
   * @return bool
   *   True if comment is in a suggestion.
   */
  public function isSuggestionComment(): bool {
    return $this->isSuggestionComment;
  }

  /**
   * Sets related suggestion if exists.
   *
   * @param RtcSuggestionNotificationEntity $suggestion
   *   The suggestion object.
   *
   * @return RtcCommentNotificationEntity
   *   The current object.
   */
  public function setRelatedSuggestion(RtcSuggestionNotificationEntity $suggestion):static {
    $this->relatedSuggestion = $suggestion;
    return $this;
  }

  /**
   * Returns related suggestion.
   *
   * @return RtcSuggestionNotificationEntity|null
   *   The suggestion object or null if not exists.
   */
  public function getRelatedSuggestion():?RtcSuggestionNotificationEntity {
    return $this->relatedSuggestion;
  }

  /**
   * Returns related suggestion author id.
   *
   * @return int|null
   *   The author id.
   */
  public function getRelatedSuggestionAuthorId(): ?int {
    return $this->relatedSuggestion?->getAuthorId();
  }

}
