<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

/**
 * Interface describing common context helper methods.
 */
interface ContextHelperInterface {

  /**
   * Returns an array of strings with document detected changes.
   *
   * @param string $context
   *   Document content.
   * @param bool $onlyInserts
   *   Flag for determining type of changes to be selected.
   */
  public function getDocumentChangesContext(string $context, bool $onlyInserts = FALSE): array;

}
