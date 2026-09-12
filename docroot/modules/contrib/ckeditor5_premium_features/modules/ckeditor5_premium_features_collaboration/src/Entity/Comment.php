<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the CKEditor5 Premium features "Comment" entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_comment",
 *   label = @Translation("CKEditor5 Comment"),
 *   base_table = "ckeditor5_comment",
 *   internal = TRUE,
 *   entity_keys = {
 *      "id" = "id",
 *      "uid" = "uid",
 *      "entity_type" = "entity_type",
 *      "entity_id" = "entity_id",
 *      "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage",
 *     "storage_schema" = "Drupal\ckeditor5_premium_features_collaboration\Entity\CommentStorageSchema",
 *     "access" = "Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityAccessControlHandler",
 *   }
 * )
 */
class Comment extends CollaborationEntityBase implements CommentInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['thread_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Thread ID'))
      ->setRequired(TRUE)
      ->setDescription(t('The comment thread ID'));

    $fields['content'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Content'))
      ->setDescription(t('The content of the comment.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getNormalizationMapping(bool $reversed): array {
    return [
      'threadId' => 'thread_id',
      'content' => 'content',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $data = parent::toArray();
    $data = [
      'content' => $this->getContent(),
      'thread_id' => $this->getThreadId(),
    ] + $data;

    $normalized = static::normalize($data, TRUE);
    $normalized['commentId'] = $normalized['id'];
    unset($normalized['id']);

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadId(): string {
    return (string) $this->get('thread_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setThreadId(string $id): static {
    return $this->setMachineName('thread_id', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getThread(): array {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage $storage */
    $storage = $this->entityTypeManager()->getStorage(self::ENTITY_TYPE_ID);

    return $storage->getCommentsThread(
      $this->getEntityTypeTargetId(),
      $this->getEntityId(),
      $this->getThreadId()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): ?string {
    $field = $this->get('content');

    return $field->isEmpty() ? NULL : $field->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentPlain(): string|null {
    $content = $this->getContent();
    if (empty($content)) {
      return NULL;
    }

    return str_replace(chr(0xC2) . chr(0xA0), ' ', html_entity_decode(strip_tags($content)));
  }

  /**
   * {@inheritdoc}
   */
  public function setContent(string $content): static {
    $this->set('content', $content);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIsReply(bool $is_reply): void {
    $attributes = $this->getAttributes();
    $attributes['is_reply'] = $is_reply;

    $this->setAttributes($attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function isReply(): bool {
    $attributes = $this->getAttributes();

    return !empty($attributes['is_reply']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPosition(): int {
    $attributes = $this->getAttributes();

    return $attributes['position'] ?? -1;
  }

}
