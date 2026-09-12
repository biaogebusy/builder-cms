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
 * Defines the CKEditor5 Premium features "Revision" entity.
 *
 * @ContentEntityType(
 *   id = "ckeditor5_revision",
 *   label = @Translation("CKEditor5 Revision"),
 *   base_table = "ckeditor5_revision",
 *   internal = TRUE,
 *   entity_keys = {
 *      "id" = "id",
 *      "uid" = "uid",
 *      "entity_type" = "entity_type",
 *      "entity_id" = "entity_id",
 *      "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "storage" = "Drupal\ckeditor5_premium_features_collaboration\Entity\RevisionStorage",
 *     "storage_schema" = "Drupal\ckeditor5_premium_features\Entity\CollaborationStorageSchema",
 *     "access" = "Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityAccessControlHandler",
 *   }
 * )
 */
class Revision extends CollaborationEntityBase implements RevisionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRequired(TRUE)
      ->setDescription(t('The name of the revision'));

    $fields['authors'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Author IDs'))
      ->setRequired(TRUE)
      ->setDescription(t('The IDs of the revision authors'));

    $fields['diff_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Diff'))
      ->setSetting('json', TRUE)
      ->setDescription(t('The diff data'));

    $fields['current_version'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Current version'))
      ->setRequired(TRUE)
      ->setDescription(t('The current revision version number'));

    $fields['previous_version'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Previous version'))
      ->setRequired(TRUE)
      ->setDescription(t('The previous revision version number'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getNormalizationMapping(bool $reversed): array {
    return [
      'name' => 'name',
      'authorsIds' => 'authors',
      'creatorId' => 'creator',
      'diffData' => 'diff_data',
      'toVersion' => 'current_version',
      'fromVersion' => 'previous_version',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    // If the revision is initial, strip the UUID from the id.
    $id = $this->id();
    if (str_starts_with((string) $id, 'initial_')) {
      $id = 'initial';
    }

    // Check if the revision is in "draft" state.
    $is_draft = $this->getAttributes()['draft'] ?? FALSE;

    // Convert the revision to an array.
    $data = parent::toArray();
    unset($data['authorId']);
    $data = [
      'id' => $id,
      'name' => $this->getName() ?: '',
      'creator' => $is_draft ? NULL : $this->getAuthor()?->id(),
      'createdAt' => gmdate('Y-m-d\TH:i:s.v\Z', $this->getCreatedTime()),
      'authors' => $this->getAuthors(),
      'diff_data' => $this->getDiffData(),
      'current_version' => $this->getCurrentVersion(),
      'previous_version' => $this->getPreviousVersion(),
    ] + $data;

    return static::normalize($data, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function normalize(array $data, bool $reversed = FALSE): array {
    if (!$reversed) {
      // Restrict ID to alphanumeric and underscores.
      $data['id'] = preg_replace('/[^[:alnum:]_]/', '', $data['id']);

      // Read the datetime.
      $data['createdAt'] = strtotime($data['createdAt'] ?: '') ?: 0;
    }

    return parent::normalize($data, $reversed);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): ?string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(?string $name): static {
    return $this->set('name', $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthors(): array {
    $value = $this->get('authors')->value;

    return (array) unserialize($value);
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthors(array $ids): static {
    $ids = array_map(fn ($id) => (string) $id, $ids);
    $ids = array_unique($ids);

    // @todo the list of ids should be sanitized first.
    return $this->set('authors', serialize($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffData(): array {
    return $this->getJsonFieldValue('diff_data');
  }

  /**
   * {@inheritdoc}
   */
  public function setDiffData(array|string $data): static {
    return $this->setJsonFieldValue('diff_data', $data);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentVersion(): int {
    return (int) $this->get('current_version')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentVersion(int $version): static {
    return $this->set('current_version', $version);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousVersion(): int {
    return (int) $this->get('previous_version')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPreviousVersion(int $version): static {
    return $this->set('previous_version', $version);
  }

}
