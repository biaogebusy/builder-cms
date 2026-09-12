<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Generator;

/**
 * Defines the interface for the token generators.
 */
interface TokenGeneratorInterface {

  /**
   * Generates the token.
   *
   * @param string|null $filterFormatId
   *   The filter format id.
   *
   * @return string
   *   The token.
   */
  public function generate(?string $filterFormatId): string;

}
