<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_realtime_collaboration\Entity;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\UserInterface;

/**
 * Base class of rtc notification class.
 */
abstract class RtcNotificationEntityBase implements RtcNotificationEntityInterface {

  /**
   * The id.
   *
   * @var string
   */
  private string $id;

  /**
   * The author.
   *
   * @var \Drupal\user\UserInterface|null
   */
  private ?UserInterface $author;

  /**
   * Entity type target id.
   *
   * @var string
   */
  private string $entityTypeTargetId;

  /**
   * Referenced entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  private FieldableEntityInterface $referencedEntity;

  /**
   * Thread.
   *
   * @var array
   */
  private array $thread;

  /**
   * Thread id.
   *
   * @var string
   */
  private string $threadId;

  /**
   * {@inheritDoc}
   */
  public function setId($id):static {
    $this->id = $id;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * {@inheritDoc}
   */
  public function getAuthor(): ?UserInterface {
    return $this->author;
  }

  /**
   * {@inheritDoc}
   */
  public function setAuthor(?UserInterface $author): static {
    $this->author = $author;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getAuthorId(): int {
    return intval($this->author?->id());
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityTypeTargetId(): string {
    return $this->entityTypeTargetId;
  }

  /**
   * {@inheritDoc}
   */
  public function setEntityTypeTargetId(string $id): static {
    $this->entityTypeTargetId = $id;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getReferencedEntity(): FieldableEntityInterface {
    return $this->referencedEntity;
  }

  /**
   * {@inheritDoc}
   */
  public function setReferencedEntity(FieldableEntityInterface $entity): static {
    $this->referencedEntity = $entity;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function setThread(array $thread): static {
    $this->thread = $thread;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getThread(): array {
    return $this->thread;
  }

  /**
   * {@inheritDoc}
   */
  public function setThreadId(string $threadId): static {
    $this->threadId = $threadId;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getThreadId(): string {
    return $this->threadId;
  }

  /**
   * {@inheritDoc}
   */
  public function id(): string {
    return $this->id;
  }

}
