<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Entity;

use Drupal\filter\FilterFormatInterface;

/**
 * Defines the storage doing content filtering.
 */
interface CollaborationContentFilteringStorageInterface {

  /**
   * Sets format filter property.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   Filter Format object.
   */
  public function setSourceFilterFormat(FilterFormatInterface $filter_format): void;

  /**
   * Process passed source data and does the filtering.
   *
   * @param array $source_data
   *   Storage source data.
   */
  public function filterSourceData(array &$source_data): void;

}
