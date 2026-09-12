<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides the methods used/reused by the collaboration entities.
 *
 * @todo May be a parent class in the future.
 */
trait CollaborationEntityStorageTrait {

  /**
   * Document original content (before submitting).
   *
   * @var string
   */
  protected string $originalDocument;

  /**
   * New Document content (after submitting).
   *
   * @var string
   */
  protected string $newDocument;

  /**
   * Loads the entities by the parent/context entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The context entity.
   * @param string|null $item_key_filter
   *   Key attribute that is used to filter results with different values.
   *
   * @return array|\Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface[]
   *   The entities matching the given entity.
   */
  public function loadByEntity(EntityInterface $entity, ?string $item_key_filter = NULL): array {
    if (!$entity->uuid()) {
      return [];
    }

    /** @var \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityBase[] $entities */
    $entities = $this->loadByProperties([
      'entity_id' => $entity->uuid(),
      'langcode' => $entity->language()->getId(),
      'entity_type' => $entity->getEntityTypeId(),
    ]);

    return array_filter($entities, function ($item) use ($item_key_filter, $entity) {
      $attributes = $item->getAttributes();
      if ($entity instanceof Paragraph && $entity->isNew()) {
        $access = $item->access('view_new');
      }
      else {
        $access = $item->access('view');
      }
      return $access && isset($attributes['key']) && ($item_key_filter == NULL || $attributes['key'] == $item_key_filter);
    });
  }

  /**
   * Returns an array with common attributes for collaboration entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Related entity.
   * @param string $item_key
   *   Related entity field key.
   */
  public function getCommonData(ContentEntityInterface $entity, string $item_key): array {
    return [
      'key' => $item_key,
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->uuid(),
      'langcode' => $entity->language()->getId(),
    ];
  }

  /**
   * Returns a list of collaboration entities attributes.
   *
   * @param array $source_data
   *   Collaboration source array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Related entity.
   * @param string $item_key
   *   Related entity field key.
   */
  public function processSourceData(array $source_data, ContentEntityInterface $entity, string $item_key): array {
    $entity_list = [];
    foreach ($source_data as $element_data) {
      if (empty($element_data['id'])) {
        continue;
      }
      if ($this instanceof StorageIdSpecificationAwareInterface) {
        if ($this->isCommonId($element_data['id'])) {
          $element_data['id'] = sprintf(
            '%s_%s_%s_%s',
            $element_data['id'],
            str_replace('-', '', $entity->uuid()),
            str_replace('-', '', $item_key),
            $entity->language()->getId()
          );
        }
      }

      $element_data = array_merge($element_data, $this->getCommonData($entity, $item_key));

      $entity_list[] = $element_data;
    }

    return $entity_list;
  }

  /**
   * Sets the source document original (before submitting) content .
   *
   * @param string $content
   *   String with document content.
   */
  public function setDocumentOriginalValue(string $content): void {
    $this->originalDocument = $content;
  }

  /**
   * Returns stored document original value or NULL if not set.
   */
  public function getDocumentOriginalValue(): ?string {
    return $this->originalDocument ?? NULL;
  }

  /**
   * Sets the new source document (after submitting) content .
   *
   * @param string $content
   *   String with document content.
   */
  public function setDocumentNewValue(string $content): void {
    $this->newDocument = $content;
  }

  /**
   * Returns stored document new value or NULL if not set.
   */
  public function getDocumentNewValue(): ?string {
    return $this->newDocument ?? NULL;
  }

}
