<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the collaboration entities storage methods.
 */
interface CollaborationEntityStorageInterface {

  /**
   * Creates the CKEDitor5 collaboration entity.
   *
   * @param array $raw_data
   *   The raw data to be used in the creation.
   *
   * @return \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface|null
   *   The created entity or NULL if not added.
   */
  public function add(array $raw_data): CollaborationEntityInterface|NULL;

  /**
   * Updates the CKEDitor5 collaboration entity.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $entity
   *   The suggestion entity.
   * @param array $raw_data
   *   The raw data to be updated.
   *
   * @return \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface|null
   *   The created entity or NULL if deleted.
   */
  public function update(CollaborationEntityInterface $entity, array $raw_data): CollaborationEntityInterface|NULL;

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
  public function processSourceData(array $source_data, ContentEntityInterface $entity, string $item_key): array;

}
