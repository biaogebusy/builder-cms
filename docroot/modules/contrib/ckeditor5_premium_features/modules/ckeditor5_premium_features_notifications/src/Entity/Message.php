<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface;
use Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface;
use Drupal\ckeditor5_premium_features_notifications\Plugin\Notification\NotificationMessageFactoryInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the CKEditor5 Premium features "Message" entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_message",
 *   label = @Translation("CKEditor5 Message"),
 *   base_table = "ckeditor5_message",
 *   entity_keys = {
 *      "id" = "id",
 *      "uid" = "uid",
 *      "entity_type" = "entity_type",
 *      "entity_id" = "entity_id",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\ckeditor5_premium_features_notifications\Entity\MessageStorage",
 *     "storage_schema" = "Drupal\ckeditor5_premium_features_notifications\Entity\MessageStorageSchema",
 *   }
 * )
 */
class Message extends ContentEntityBase implements MessageInterface {

  use StringTranslationTrait;
  use CKeditorPremiumLoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Message ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE)
      ->setSetting('machine_name', TRUE)
      ->setDescription(t('The target entity type.'));

    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The Entity ID.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the message was created.'))
      ->setRequired(TRUE)
      ->setStorageRequired(TRUE);

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRequired(TRUE)
      ->setDescription(t('The time that the message was changed.'))
      ->setStorageRequired(TRUE);

    $fields['sent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sent'))
      ->setRequired(TRUE)
      ->setDescription(t('A boolean indicating whether this message was sent.'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function appendItem(string $itemEntityType,
                             string $itemEntityId,
                             string $messageType,
                             string $eventType,
                             string $messageContent,
                             string $uid,
                             string $key,
                             ?string $refUid = NULL): int {
    $saveResult = $this->entityTypeManager()->getStorage(MessageItemInterface::ENTITY_TYPE_ID)
      ->create([
        'message_id' => $this->id(),
        'entity_type' => $itemEntityType,
        'entity_id' => $itemEntityId,
        'message_type' => $messageType,
        'event_type' => $eventType,
        'uid' => $uid,
        'key_id' => $key,
        'ref_uid' => $refUid,
        'message_content' => $messageContent,
      ])->save();

    if ($saveResult == SAVED_NEW || $saveResult == SAVED_UPDATED) {
      $this->set('updated', time());
      $this->save();
    }

    return $saveResult;
  }

  /**
   * Returns related message items.
   */
  public function getRelatedMessagesItems(): array {
    try {
      $messageItems = $this->entityTypeManager()
        ->getStorage(MessageItemInterface::ENTITY_TYPE_ID)
        ->loadByProperties([
          'message_id' => $this->id(),
        ]);
      return $messageItems;
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItems(): array {
    try {
      $messageItems = $this->getRelatedMessagesItems();

      $groupedMessageItems = [];

      /** @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface $item */
      foreach ($messageItems as $item) {
        switch ($item->getRelatedEntityType()) {
          case CommentInterface::ENTITY_TYPE_ID:
            /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentInterface $entity */
            $entity = $item->getRelatedEntity();

            if (!$entity) {
              break;
            }

            if (isset($groupedMessageItems[$entity->getThreadId()])) {
              /** @var \Drupal\ckeditor5_premium_features_notifications\Entity\MessageItemInterface $previousMessageItem */
              $previousMessageItem = $groupedMessageItems[$entity->getThreadId()];
              if ($previousMessageItem->getRelatedEntityType() == SuggestionInterface::ENTITY_TYPE_ID) {
                break;
              }
            }
            $groupedMessageItems[$entity->getThreadId()] = $item;
            break;

          case SuggestionInterface::ENTITY_TYPE_ID:
            /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionInterface $entity */
            $entity = $item->getRelatedEntity();

            if (!$entity) {
              break;
            }
            $key = !empty($entity->getChainId()) ? $entity->getChainId() : $entity->id();
            $groupedMessageItems[$key] = $item;
            break;

          default:
            $groupedMessageItems[NotificationMessageFactoryInterface::CKEDITOR5_MESSAGE_MENTION_DOCUMENT . '-' . $item->getKeyId() . '-' . $item->getUid() . '-' . $item->id()] = $item;
            break;
        }
      }

      return $groupedMessageItems;
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?UserInterface {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    $entityId = $this->get('entity_id')->getString();
    $entityType = $this->get('entity_type')->getString();
    try {
      $entity = $this->entityTypeManager()->getStorage($entityType)->load($entityId);

      if (method_exists($entity, 'getTitle')) {
        return $entity->getTitle();
      }
      if (method_exists($entity, 'label')) {
        return $entity->label();
      }
    }
    catch (EntityStorageException $e) {
      $this->logException("Exception occurred when searching for a related entity.", $e);
    }

    return $this->t('New activity in a document')->render();
  }

}
