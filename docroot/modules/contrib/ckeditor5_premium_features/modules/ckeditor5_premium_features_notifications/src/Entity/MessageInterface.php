<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\user\UserInterface;

/**
 * Provides the interface for the CKEditor5 "Message" entity.
 */
interface MessageInterface {

  public const ENTITY_TYPE_ID = 'ckeditor5_message';

  /**
   * Stores new message item related to current message.
   *
   * @param string $itemEntityType
   *   Type of the message item entity.
   * @param string $itemEntityId
   *   ID of the message item entity.
   * @param string $messageType
   *   Type of message.
   * @param string $eventType
   *   Type of event.
   * @param string $messageContent
   *   Content of the document.
   * @param string $uid
   *   ID of the message item author.
   * @param string $key
   *   ID of the field with related document.
   * @param string|null $refUid
   *   ID of optionally referenced user.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  public function appendItem(string $itemEntityType,
                             string $itemEntityId,
                             string $messageType,
                             string $eventType,
                             string $messageContent,
                             string $uid,
                             string $key,
                             ?string $refUid = NULL): int;

  /**
   * Returns related message items.
   *
   * @return \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface[]
   *   Message items list.
   */
  public function getItems();

  /**
   * Returns message recipient.
   *
   * @return \Drupal\user\UserInterface|null
   *   User entity if found.
   */
  public function getUser(): ?UserInterface;

  /**
   * Returns message title.
   */
  public function getTitle(): string;

}
