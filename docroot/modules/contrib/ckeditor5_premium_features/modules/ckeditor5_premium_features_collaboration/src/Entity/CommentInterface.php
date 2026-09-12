<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Provides the interface for the CKEditor5 "Comment" entity.
 */
interface CommentInterface {

  public const ENTITY_TYPE_ID = 'ckeditor5_comment';

  /**
   * Gets the thread ID.
   *
   * @return string
   *   The ID of the comment thread.
   */
  public function getThreadId(): string;

  /**
   * Sets the thread ID.
   *
   * @param string $id
   *   The ID to set.
   *
   * @return static
   *   The current object.
   */
  public function setThreadId(string $id): static;

  /**
   * Returns comment thread that the current entity is a part of.
   */
  public function getThread(): array;

  /**
   * Gets the comment content.
   *
   * @return string|null
   *   The content of the comment, defaults to null.
   */
  public function getContent(): ?string;

  /**
   * Returns content without HTML markup.
   */
  public function getContentPlain(): string|null;

  /**
   * Sets the comment content.
   *
   * @param string $content
   *   The content to set.
   *
   * @return static
   *   The current object.
   */
  public function setContent(string $content): static;

  /**
   * Gets the is_reply attribute value.
   */
  public function isReply(): bool;

  /**
   * Sets the is_reply attribute value.
   *
   * @param bool $is_reply
   *   Is reply flag.
   */
  public function setIsReply(bool $is_reply): void;

  /**
   * Returns position attribute.
   */
  public function getPosition(): int;

}
