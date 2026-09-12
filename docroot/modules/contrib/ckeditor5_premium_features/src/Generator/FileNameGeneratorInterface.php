<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Generator;

/**
 * Defines the interface for the name file generator.
 */
interface FileNameGeneratorInterface {

  /**
   * Generate file name based on url/alias.
   *
   * @return string
   *   File name.
   */
  public function generateFromRequest(): string;

  /**
   * Add Extension to filename.
   *
   * @param string $filename
   *   Generated filename.
   * @param string $extension
   *   Extension file to add.
   */
  public function addExtensionFile(string &$filename, string $extension): void;

}
