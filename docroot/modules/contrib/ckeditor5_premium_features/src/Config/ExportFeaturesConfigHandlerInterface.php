<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Config;

/**
 * Defines the interface for handling export features settings configuration.
 */
interface ExportFeaturesConfigHandlerInterface extends ImportExportFeaturesConfigHandlerInterface {

  /**
   * Gets the converter options.
   *
   * It is filtering the empty values
   * in order to use the plugin defaults.
   *
   * @return array
   *   The converter options.
   */
  public function getConverterOptions(): array;

}
