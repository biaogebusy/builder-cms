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
 * Defines the CKEditor5 Premium features "Suggestion" entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_suggestion",
 *   label = @Translation("CKEditor5 Suggestion"),
 *   base_table = "ckeditor5_suggestion",
 *   internal = TRUE,
 *   entity_keys = {
 *      "id" = "id",
 *      "uid" = "uid",
 *      "entity_type" = "entity_type",
 *      "entity_id" = "entity_id",
 *      "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorage",
 *     "storage_schema" = "Drupal\ckeditor5_premium_features_collaboration\Entity\SuggestionStorageSchema",
 *     "access" = "Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityAccessControlHandler",
 *   }
 * )
 */
class Suggestion extends CollaborationEntityBase implements SuggestionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Suggestion type'))
      ->setRequired(TRUE)
      ->setSetting('machine_name', TRUE)
      ->setDescription(t('The editor suggestion type.'));

    $fields['chain_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Suggestion chain ID'))
      ->setRequired(FALSE);

    $fields['has_comments'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Has comments'))
      ->setRequired(TRUE)
      ->setDefaultValue(FALSE)
      ->setDescription(t('A boolean indicating whether the suggestion has comments.'));

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Data'))
      ->setSetting('json', TRUE)
      ->setDescription(t('The suggestion data.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getNormalizationMapping(bool $reversed): array {
    return [
      'type' => 'type',
      'hasComments' => 'has_comments',
      'data' => 'data',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $data = parent::toArray();
    $data = [
      'type' => $this->getType(),
      'has_comments' => $this->hasComments(),
      'data' => $this->getData() ?: NULL,
    ] + $data;

    return static::normalize($data, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return (string) $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType(string $type): static {
    return $this->setMachineName('type', $type);
  }

  /**
   * {@inheritdoc}
   */
  public function getData(bool $raw = FALSE): string|array {
    return $this->getJsonFieldValue('data', $raw);
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array|string $data): static {
    return $this->setJsonFieldValue('data', $data);
  }

  /**
   * {@inheritdoc}
   */
  public function setCommentState(bool $state): static {
    $this->set('has_comments', $state);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasComments(): bool {
    return (bool) $this->get('has_comments')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChainId(): string {
    return (string) $this->get('chain_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChainId(?string $chain_id = NULL): static {
    return $this->set('chain_id', $chain_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isInChain(): bool {
    return !empty($this->getChainId());
  }

  /**
   * {@inheritdoc}
   */
  public function isHeadOfChain(): bool {
    return $this->isInChain() && $this->id() == $this->getChainId();
  }

  /**
   * {@inheritdoc}
   */
  public function getChain(): array {
    $chainFromDb = $this->entityTypeManager()->getStorage(self::ENTITY_TYPE_ID)
      ->loadByProperties([
        'chain_id' => $this->getChainId(),
      ]);

    if (empty($chainFromDb)) {
      return [
        $this,
      ];
    }

    return $chainFromDb;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string|NULL {
    $attributes = $this->getAttributes();

    return $attributes['status'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getThread(): array {
    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CommentsStorage $storage */
    $storage = $this->entityTypeManager()->getStorage(CommentInterface::ENTITY_TYPE_ID);

    $threadId = $this->getChainId() ? $this->getChainId() : $this->getId();

    return $storage->getCommentsThread(
      $this->getEntityTypeTargetId(),
      $this->getEntityId(),
      $threadId
    );

  }

}
