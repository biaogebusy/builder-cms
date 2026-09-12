<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\ckeditor5_premium_features\CKeditorDateFormatterTrait;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features\Event\CollaborationEventBase;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines notification message item entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_message_item",
 *   label = @Translation("CKEditor5 Message Item"),
 *   base_table = "ckeditor5_message_item",
 *   entity_keys = {
 *      "id" = "id",
 *      "message_id" = "message_id",
 *   },
 * )
 */
class MessageItem extends ContentEntityBase implements MessageItemInterface {

  use CKeditorDateFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Message Item ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['message_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Message ID'))
      ->setSetting('target_type', 'ckeditor5_message')
      ->setRequired(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Item entity type'))
      ->setRequired(TRUE)
      ->setSetting('machine_name', TRUE)
      ->setDescription(t('The target entity type.'));

    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Item entity ID'))
      ->setDescription(t('The Entity ID.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setRequired(TRUE);

    $fields['key_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field Key ID'));

    $fields['ref_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Referenced User'))
      ->setSetting('target_type', 'user');

    $fields['message_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message type'))
      ->setRequired(TRUE)
      ->setDescription(t('The message type.'));

    $fields['event_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message item event type.'))
      ->setRequired(TRUE)
      ->setDescription(t('The message event type.'));

    $fields['message_content'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message item event type.'))
      ->setDescription(t('The message content.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('message_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageContent(): string {
    return $this->get('message_content')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getEventType(): string {
    return $this->get('event_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent(): CollaborationEventBase {
    $evt = new CollaborationEventBase(
      $this->getRelatedEntity(),
      $this->getUser(),
      $this->getEventType()
    );

    $evt->setRelatedDocumentKey($this->getKeyId());
    $evt->setOriginalContent($this->getMessageContent());
    $evt->setReferencedUserId($this->getRefUid());
    return $evt;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedEntityId(): string {
    return $this->get('entity_id')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedEntityType(): string {
    return $this->get('entity_type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedEntity(): EntityInterface|null {
    try {
      return $this->entityTypeManager()
        ->getStorage($this->getRelatedEntityType())
        ->load($this->getRelatedEntityId());
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUid(): string {
    return $this->get('uid')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?UserInterface {
    try {
      return $this->entityTypeManager()
        ->getStorage('user')
        ->load($this->getUid());
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Returns referenced entity field ID.
   */
  public function getKeyId(): ?string {
    return $this->get('key_id')->getString();
  }

  /**
   * Returns referenced user ID.
   */
  public function getRefUid(): ?string {
    return $this->get('ref_uid')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedDate($format = 'medium'): string {
    return $this->format($this->getCreatedTime(), $format);
  }

  /**
   * {@inheritdoc}
   */
  public function getThread(): array {
    switch ($this->getRelatedEntityType()) {
      case CommentInterface::ENTITY_TYPE_ID:
        /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Comment $comment */
        $comment = $this->getRelatedEntity();
        return $comment->getThread();

      case SuggestionInterface::ENTITY_TYPE_ID:
        /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\Suggestion $suggestion */
        $suggestion = $this->getRelatedEntity();
        return $suggestion->getThread();
    }

    return [];
  }

}
