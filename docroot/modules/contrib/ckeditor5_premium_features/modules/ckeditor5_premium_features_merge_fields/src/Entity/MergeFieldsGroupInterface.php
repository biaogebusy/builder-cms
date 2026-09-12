<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Merge Fields Group entities.
 */
interface MergeFieldsGroupInterface extends ConfigEntityInterface {

  /**
   * Gets the Merge Fields Group weight.
   *
   * @return int
   *   The Merge Fields Group weight.
   */
  public function getWeight(): int;

  /**
   * Sets the Merge Fields Group weight.
   *
   * @param int $weight
   *   The Merge Fields Group weight.
   *
   * @return \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsGroupInterface
   *   The called Merge Fields Group entity.
   */
  public function setWeight(int $weight): MergeFieldsGroupInterface;

}
