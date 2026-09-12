<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Entity;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\UserInterface;

/**
 * Defines the interface for the rtc notification entity.
 */
interface RtcNotificationEntityInterface {

  /**
   * Set id.
   *
   * @param string $id
   *   The id.
   *
   * @return RtcNotificationEntityInterface
   *   The current object.
   */
  public function setId(string $id): static;

  /**
   * Returns id.
   *
   * @return string
   *   The id.
   */
  public function getId(): string;

  /**
   * Returns author.
   *
   * @return \Drupal\user\UserInterface|null
   *   The author or null.
   */
  public function getAuthor(): ?UserInterface;

  /**
   * Set author.
   *
   * @param \Drupal\user\UserInterface|null $author
   *   The author.
   *
   * @return RtcNotificationEntityInterface
   *   The current object.
   */
  public function setAuthor(?UserInterface $author): static;

  /**
   * Returns author id.
   *
   * @return int
   *   The id.
   */
  public function getAuthorId(): int;

  /**
   * Returns entity type target id.
   *
   * @return string
   *   The entity type target id.
   */
  public function getEntityTypeTargetId(): string;

  /**
   * Sets entity type target id.
   *
   * @param string $id
   *   The id.
   *
   * @return RtcNotificationEntityInterface
   *   The current object.
   */
  public function setEntityTypeTargetId(string $id): static;

  /**
   * Returns referenced entity.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Referenced entity.
   */
  public function getReferencedEntity(): FieldableEntityInterface;

  /**
   * Sets referenced entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Referenced entity.
   *
   * @return RtcNotificationEntityInterface
   *   The current object.
   */
  public function setReferencedEntity(FieldableEntityInterface $entity): static;

  /**
   * Sets array of threads.
   *
   * @param array $thread
   *   Array of threads.
   *
   * @return RtcNotificationEntityInterface
   *   The current object.
   */
  public function setThread(array $thread): static;

  /**
   * Returns object thread.
   *
   * @return array
   *   Thread.
   */
  public function getThread(): array;

  /**
   * Sets thread id.
   *
   * @param string $threadId
   *   The current object.
   */
  public function setThreadId(string $threadId): static;

  /**
   * Returns thread id.
   *
   * @return string
   *   The thread id.
   */
  public function getThreadId(): string;

  /**
   * Returns object id.
   *
   * @return string
   *   The id.
   */
  public function id(): string;

  /**
   * Returns entity type id.
   *
   * @return string
   *   The type id.
   */
  public function getEntityTypeId(): string;

}
