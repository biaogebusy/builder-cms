<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Provides the interface for the storages that may require normalization.
 */
interface StorageIdSpecificationAwareInterface {

  /**
   * Checks if the given entity ID is common (not unique).
   *
   * In this case, we can't just save it to entity storage.
   * First, we have to make the ID unique.
   *
   * @param string $id
   *   The ID to check.
   *
   * @return bool
   *   Returns TRUE if the ID is common.
   */
  public function isCommonId(string $id): bool;

}
