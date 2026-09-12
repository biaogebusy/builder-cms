<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides the interface for the CKEditor5 "Message Item" entity.
 */
interface MessageItemInterface {

  public const ENTITY_TYPE_ID = 'ckeditor5_message_item';

  /**
   * Getter for item type.
   */
  public function getType(): string;

  /**
   * Getter for message content.
   */
  public function getMessageContent(): string;

  /**
   * Getter for event type.
   */
  public function getEventType(): string;

  /**
   * Getter for event object suitable for current item.
   */
  public function getEvent(): CollaborationEventBase;

  /**
   * Getter for related entity ID.
   */
  public function getRelatedEntityId(): string;

  /**
   * Getter for related entity type.
   */
  public function getRelatedEntityType(): string;

  /**
   * Returns related document entity.
   */
  public function getRelatedEntity(): EntityInterface|null;

  /**
   * Returns uid field value.
   */
  public function getUid(): string;

  /**
   * Returns referenced User entity.
   */
  public function getUser(): ?UserInterface;

  /**
   * Returns referenced entity field ID.
   */
  public function getKeyId(): ?string;

  /**
   * Gets the node creation timestamp.
   *
   * @return int
   *   Creation timestamp of the node.
   */
  public function getCreatedTime(): int;

  /**
   * Returns formatted date  of creation.
   *
   * @param string $format
   *   Format name.
   */
  public function getCreatedDate(string $format = 'medium'): string;

  /**
   * Returns a related collaboration entity thread.
   */
  public function getThread(): array;

}
