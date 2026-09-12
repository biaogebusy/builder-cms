<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_cloud_services\Entity;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Provides the storage class for the Channel entity.
 */
class ChannelStorage extends SqlContentEntityStorage {

  use CKeditorPremiumLoggerChannelTrait;

  /**
   * Creates a new channel entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity item.
   * @param string $channel_id
   *   Channel ID.
   * @param string $element_id
   *   ID of the field element.
   *
   * @return \Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelInterface
   *   Channel entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createChannel(EntityInterface $entity, string $channel_id, string $element_id): ChannelInterface {
    $properties = [
      'id' => $channel_id,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->uuid(),
      'created' => time(),
      'key_id' => $element_id,
      'langcode' => $this->getEntityLanguageId($entity),
    ];

    $channel = parent::create($properties);
    try {
      $channel->save();
      // Delete old channels.
      $this->deleteChannels($entity, $element_id, $channel_id);

    }
    catch (EntityStorageException) {
      $channel_stored = $this->load($channel_id);

      // Below is only a backward compatibility with sites using the Channel
      // entities, but before adding the `key_id` property.
      if ($channel_stored instanceof Channel && $channel_stored->hasField('key_id') &&
        empty($channel_stored->get('key_id')->getString())) {
        $channel_stored->set('key_id', $element_id);
        $channel_stored->save();

        return $channel_stored;
      }
    }

    return $channel;
  }

  /**
   * Deletes channels in an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove channels from.
   * @param string|null $element_id
   *   Optional. Element id - channels associated to this element will be
   *   removed. If empty all elements channels will be removed.
   * @param string|null $channel_id
   *   Optional. Channel id to preserve from deleting.
   */
  public function deleteChannels(EntityInterface $entity, ?string $element_id = NULL, ?string $channel_id = NULL): void {
    $language_id = $this->getEntityLanguageId($entity);
    $query = $this->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('entity_id', $entity->uuid());
    $query->condition('langcode', $language_id);
    if ($element_id) {
      $query->condition('key_id', $element_id);
    }
    if ($channel_id) {
      $query->condition('id', $channel_id, '!=');
    }

    $ids = $query->execute();
    $channels = $this->loadMultiple($ids);
    $this->delete($channels);
  }

  /**
   * Returns a channel entity referencing passed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity item.
   * @param string $element_id
   *   ID of the field element.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadByEntity(EntityInterface $entity, string $element_id): ?ChannelInterface {
    $properties = [
      'entity_id' => $entity->uuid(),
      'entity_type' => $entity->getEntityTypeId(),
      'key_id' => $element_id,
      'langcode' => $this->getEntityLanguageId($entity),
    ];

    $channel = $this->entityTypeManager->getStorage(ChannelInterface::ENTITY_TYPE_ID)
      ->loadByProperties($properties);

    if (empty($channel)) {
      return NULL;
    }

    return reset($channel);
  }

  /**
   * Returns entity language id.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity item.
   *
   * @return string
   *   Language id.
   */
  private function getEntityLanguageId(EntityInterface $entity): string {
    $entity_language = $entity->language();
    return $entity_language->getId();
  }

}
