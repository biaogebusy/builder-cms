<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_notifications\Entity;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the storage class for the Message entity.
 */
class MessageStorage extends SqlContentEntityStorage {

  use CKeditorPremiumLoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns message entity matching passed user and document parameters.
   *
   * @param string $userId
   *   ID of the related user.
   * @param string $documentId
   *   ID of the related entity.
   * @param string $documentType
   *   Type of the related entity.
   */
  public function getMessageForUserAndDocument(string $userId, string $documentId, string $documentType): Message|NULL {
    $result = $this->loadByProperties([
      'uid' => $userId,
      'entity_id' => $documentId,
      'entity_type' => $documentType,
      'sent' => 0,
    ]);

    if (empty($result)) {
      return NULL;
    }

    return reset($result);
  }

  /**
   * Creates message entity.
   *
   * @param string $userID
   *   User ID.
   * @param string $entityId
   *   Entity ID.
   * @param string $entityType
   *   Entity type.
   *
   * @return \Drupal\Core\Entity\EntityInterface|mixed
   *   Returns created entity.
   */
  public function createMessage(string $userID, string $entityId, string $entityType) {
    return parent::create([
      'uid' => $userID,
      'entity_type' => $entityType,
      'entity_id' => $entityId,
    ]);
  }

  /**
   * Get oldest unsent messages.
   *
   * @param int $range
   *   Max quantity of getting messages.
   * @param int $timeInterval
   *   Time interval in minutes.
   *
   * @return array
   *   Array of message entities.
   */
  public function getOldestMessages(int $range, int $timeInterval = 0):array {
    $query = $this->getQuery()
      ->accessCheck(FALSE)
      ->condition('sent', 0)
      ->sort('created', 'ASC')
      ->range(0, $range);

    if ($timeInterval > 0) {
      try {
        $desiredTimestamp = new \DateTime();
        $desiredTimestamp->sub(new \DateInterval('PT' . $timeInterval . 'M'));

        $query->condition('updated', $desiredTimestamp->getTimestamp(), '<=');
      }
      catch (\Exception $e) {
        $this->logException("Exception occurred when searching for bulk messages.", $e);
        return [];
      }
    }

    $nIDs = $query->execute();

    if (!empty($nIDs)) {
      return $this->loadMultiple($nIDs);
    }

    return [];
  }

  /**
   * Removes message item potential leftovers.
   *
   * @param \Drupal\ckeditor5_premium_features_notifications\Entity\Message $message
   *   Message that should have removed all message items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanMessageItems(Message $message): void {
    $messageItems = $this->entityTypeManager
      ->getStorage(MessageItemInterface::ENTITY_TYPE_ID)
      ->loadByProperties([
        'message_id' => $message->id(),
      ]);

    foreach ($messageItems as $item) {
      $item->delete();
    }
  }

}
