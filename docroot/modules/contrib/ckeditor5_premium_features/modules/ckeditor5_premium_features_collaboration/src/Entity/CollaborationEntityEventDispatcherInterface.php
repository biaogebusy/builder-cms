<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Interface describing a storage that supports entities event dispatching.
 */
interface CollaborationEntityEventDispatcherInterface {

  /**
   * Dispatches an event when a new entity is created.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $entity
   *   Created entity.
   */
  public function dispatchNewEntity(CollaborationEntityInterface $entity): void;

  /**
   * Dispatches an event when an entity is updated.
   *
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $oldEntity
   *   Previous entity.
   * @param \Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface $newEntity
   *   New entity.
   */
  public function dispatchUpdatedEntity(CollaborationEntityInterface $oldEntity, CollaborationEntityInterface $newEntity): void;

}
