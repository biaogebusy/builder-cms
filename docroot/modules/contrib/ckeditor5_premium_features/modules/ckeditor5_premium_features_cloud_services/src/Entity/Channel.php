<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_cloud_services\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the CKEditor5 Premium features "Channel" entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_channel",
 *   label = @Translation("CKEditor5 Channel"),
 *   base_table = "ckeditor5_channel",
 *   internal = TRUE,
 *   entity_keys = {
 *      "id" = "id",
 *      "entity_type" = "entity_type",
 *      "entity_id" = "entity_id",
 *      "langcode" = "langcode",
 *      "created" = "created",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\ckeditor5_premium_features_cloud_services\Entity\ChannelStorage",
 *     "storage_schema" = "Drupal\ckeditor5_premium_features\Entity\CollaborationStorageSchema",
 *   }
 * )
 */
class Channel extends ContentEntityBase implements ChannelInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Channel ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE)
      ->setSetting('machine_name', TRUE)
      ->setDescription(t('The target entity type.'));

    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity ID'))
      ->setRequired(TRUE)
      ->setDescription(t('The Entity ID.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the suggestion was created.'));

    $fields['key_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field Key ID'));

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setDefaultValue('x-default')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyId(): ?string {
    return (string) $this->get('key_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeyId(string $value): static {
    $this->set('key_id', $value);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityUuid(): ?string {
    return (string) $this->get('entity_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityType(): ?string {
    return (string) $this->get('entity_type')->value;
  }

}
