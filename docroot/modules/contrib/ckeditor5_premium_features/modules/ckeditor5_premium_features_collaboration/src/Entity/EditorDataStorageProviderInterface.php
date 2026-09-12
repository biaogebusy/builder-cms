<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Defines method for storages providing data to be passed to the CKEditor.
 */
interface EditorDataStorageProviderInterface {

  /**
   * Serialize the collection of entities to the editor data.
   *
   * @param array|\Drupal\ckeditor5_premium_features_collaboration\Entity\CollaborationEntityInterface[] $entities
   *   The entities to be serialized.
   *
   * @return string
   *   The serialized JSON data to be used by the editor.
   */
  public function serializeCollection(array $entities): string;

}
