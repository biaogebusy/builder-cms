<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Interface describing common collaboration settings methods.
 */
interface RevisionLimitationSettingsInterface {

  /**
   * Checks if revision quantity limitation is enabled.
   */
  public function isRevisionQuantityLimitation(): bool;

  /**
   * Checks if revision time limitation is enabled.
   */
  public function isRevisionTimeLimitation(): bool;

  /**
   * Returns a number of revisions for quantity limitation.
   */
  public function getRevisionQuantityLimit(): int;

  /**
   * Returns number of days for revisions time limitation.
   */
  public function getRevisionTimeLimit(): int;

  /**
   * Checks if any of the revisions limitation is enabled.
   */
  public function isRevisionsLimitationEnabled(): bool;

}
