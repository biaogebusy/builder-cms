<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use DateTimeInterface;
use Drupal\ckeditor5_premium_features\CKeditorDateFormatterTrait;
use Drupal\Component\Serialization\Json;
use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;

/**
 * Provides the base entity class for the collaboration entities.
 */
abstract class CollaborationEntityBase extends ContentEntityBase implements CollaborationEntityInterface {

  use CKeditorDateFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Suggestion ID'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The collaboration entity language code.'));

    // We need to have two string (non-reference) fields,
    // because the entity id is not available before
    // the entity is created. We are only able to store some temp hash.
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
      ->setDescription(t('The time that the suggestion was created.'));

    $fields['attributes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Attributes'))
      ->setSetting('json', TRUE)
      ->setDescription(t('The suggestion attributes.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $data = [
      'id' => $this->id(),
      'uid' => (string) $this->getAuthorId(),
      'created' => $this->convertTimestampToAtom($this->getCreatedTime()),
      'attributes' => $this->getAttributes(),
    ];

    return static::normalize($data, TRUE);
  }

  /**
   * Converts the timestamp to ATOM date format.
   *
   * @param int $timestamp
   *   The timestamp to be converted.
   *
   * @return string
   *   The ATOM formatted date or '0' on failure.
   */
  protected function convertTimestampToAtom(int $timestamp): string {
    try {
      $dateTime = new \DateTime();
      $dateTime->setTimestamp($timestamp);
      return $dateTime->format(\DateTimeInterface::ATOM);
    } catch (\Exception) {
      return '0';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function normalize(array $data, bool $reversed = FALSE): array {
    $mapping = static::getNormalizationMapping($reversed) + [
      'id' => 'id',
      'createdAt' => 'created',
      'entity_id' => 'entity_id',
      'entity_type' => 'entity_type',
      'attributes' => 'attributes',
    ];

    if ($reversed) {
      $mapping['authorId'] = 'uid';
      $mapping = array_flip($mapping);
    }

    $normalized = [];
    foreach ($data as $property => $value) {
      if (isset($mapping[$property])) {
        $normalized[$mapping[$property]] = $value;
      }
      else {
        $normalized[$property] = $value;
      }
    }

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->get('id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorId(): ?int {
    $field = $this->get('uid');

    return $field->isEmpty() ? NULL : (int) $field->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor(): ?UserInterface {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthor(?AccountProxyInterface $author): static {
    $this->set('uid', $author?->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage(): ?string {
    return $this->get('langcode');
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage(string $langcode): static {
    $this->set('langcode', $langcode);
    return $this;
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
  public function getEntityTypeTargetId(): string {
    return (string) $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId(): string {
    return (string) $this->get('entity_id')->value;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReferencedEntity(): ?EntityInterface {
    $entity = $this->entityTypeManager()
      ->getStorage($this->getEntityTypeTargetId())
      ->loadByProperties(['uuid' => $this->getEntityId()]);
    return reset($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeTargetId(string $id): static {
    return $this->setMachineName('entity_type', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(bool $raw = FALSE): string|array {
    return $this->getJsonFieldValue('attributes', $raw);
  }

  /**
   * {@inheritdoc}
   */
  public function setAttributes(array|string $data): static {
    return $this->setJsonFieldValue('attributes', $data);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): string|null {
    $attributes = $this->getAttributes();

    return $attributes['key'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setKey(string $key): static {
    $attributes = $this->getAttributes();
    $attributes['key'] = $key;
    $this->setAttributes($attributes);

    return $this;
  }

  /**
   * Gets the value of the fields containing the JSON data.
   *
   * @param string $field_name
   *   The name of the field.
   * @param bool $raw
   *   FALSE to return decoded, TRUE for having
   *   the raw string value.
   *
   * @return string|array
   *   The data decoded or raw.
   */
  protected function getJsonFieldValue(string $field_name, bool $raw = FALSE): array|string {
    $data = '';
    if ($this->hasField($field_name)) {
      $data = (string) $this->get($field_name)->value;
    }

    return $raw ? $data : (array) Json::decode($data);
  }

  /**
   * Sets the value of the fields containg the JSON data.
   *
   * @param string $field_name
   *   The name of the field.
   * @param array|string $data
   *   The data value (decoded or raw)
   */
  protected function setJsonFieldValue(string $field_name, array|string $data): static {
    if ($this->hasField($field_name)) {
      $data = is_array($data) ? Json::encode($data) : $data;
      $this->set($field_name, $data);
    }

    return $this;
  }

  /**
   * Sets the string as the machine name.
   *
   * Adds some sanitization methods before saving the value.
   *
   * @param string $field_name
   *   The name of the field.
   * @param string $value
   *   The value to be sanitized and stored.
   *
   * @return $this
   */
  protected function setMachineName(string $field_name, string $value): static {
    $name = Html::decodeEntities(strip_tags($value));
    $this->set($field_name, $name);

    return $this;
  }

}
