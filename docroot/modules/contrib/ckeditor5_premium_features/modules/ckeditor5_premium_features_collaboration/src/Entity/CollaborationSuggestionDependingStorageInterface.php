<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

/**
 * Defines the storage depending on suggestion entities.
 */
interface CollaborationSuggestionDependingStorageInterface {

  /**
   * Sets suggestion IDs property.
   *
   * @param array $suggestion_ids
   *   List of suggestion IDs.
   */
  public function setSuggestionIds(array $suggestion_ids): void;

  /**
   * Checks if specified ID is present on the list of suggestion IDs.
   */
  public function hasSuggestionId(string $suggestion_id): bool;

}
